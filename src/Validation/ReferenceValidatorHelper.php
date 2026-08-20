<?php

namespace Lexide\Syringe\Validation;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Reference\ReferenceHelper;

class ReferenceValidatorHelper
{

    protected ReferenceHelper $referenceHelper;
    protected ErrorHelper $errorHelper;
    protected int $maxParameterReferences;
    protected array $definitions = [];

    /**
     * @param ReferenceHelper $referenceHelper
     * @param ErrorHelper $errorHelper
     * @param int $maxParameterReferences - maximum number of parameter references inside a single parameter
     */
    public function __construct(ReferenceHelper $referenceHelper, ErrorHelper $errorHelper, int $maxParameterReferences = 100)
    {
        $this->referenceHelper = $referenceHelper;
        $this->errorHelper = $errorHelper;
        $this->maxParameterReferences = $maxParameterReferences;
    }

    /**
     * @param array $definitions
     */
    public function setDefinitions(array $definitions): void
    {
        $this->definitions = $definitions;
    }

    /**
     * @param array $definition
     * @param array $options
     * @return array
     */
    public function checkArrayForReferences(array $definition, array $options = []): array
    {
        $errors = [];
        $references = [];
        foreach ($definition as $key => $value) {
            if (is_string($key)) {
                [$keyErrors, $keyReferences] = $this->checkValueForReferences(
                    $key,
                    array_replace_recursive($options, ["skipServices" => true])
                );
                $errors = array_merge($errors, $keyErrors);
                $references = array_merge($references, $keyReferences);
            }

            $valueErrors = [];
            $valueReferences = [];

            if (is_string($value)) {
                [$valueErrors, $valueReferences] = $this->checkValueForReferences($value, $options);
            } elseif (is_array($value)) {
                [$valueErrors, $valueReferences] = $this->checkArrayForReferences($value, $options);
            }

            $errors = array_merge($errors, $valueErrors);
            $references = array_merge($references, $valueReferences);

        }
        return [$errors, $references];
    }

    /**
     * @param mixed $value
     * @param array $options
     * @return array
     */
    public function checkValueForReferences(mixed $value, array $options = []): array
    {
        $errors = [];
        if (is_array($value)) {
            $references = [];
            foreach ($value as $key => $element) {
                [$keyErrors, $keyReferences] = $this->checkValueForReferences($key, $options);
                [$elementErrors, $elementReferences] = $this->checkValueForReferences($element, $options);
                $errors = array_merge($errors, $keyErrors, $elementErrors);
                $references = array_merge($references, $keyReferences, $elementReferences);
            }
            return [$errors, $references];
        }
        if (!is_string($value)) {
            return [[], []];
        }

        $errors = array_merge($errors, $this->checkConstantReferences($value, $options));

        [$parameterErrors, $parameterReferences] = $this->checkParameterReferences($value, $options);
        $errors = array_merge($errors, $parameterErrors);

        $reference = $this->checkServiceReference($value, $options);
        if ($reference === false) {
            $errors[] = $this->errorHelper->referenceError("The reference '$value' does not exist");
        } elseif (is_string($reference)) {
            $parameterReferences[] = $reference;
        }

        return [$errors, $parameterReferences];
    }

    /**
     * @param string $value
     * @param array $options
     * @return array
     */
    public function checkParameterReferences(string $value, array $options = []): array
    {
        if (!empty($options["skipParameters"])) {
            return [[], []];
        }

        $errors = [];
        $references = [];
        $originalValue = $value;

        // sanity checking
        $counter = 0;

        while (($parameter = $this->referenceHelper->findNextParameter($value)) !== null) {
            if (!array_key_exists($parameter, $this->definitions["parameters"])) {
                $errors[] = $this->errorHelper->referenceError("The parameter '$parameter' does not exist");
            } else {
                $references[] = $parameter;
            }
            // remove the reference so we skip over it
            $value = $this->referenceHelper->replaceParameterReference($value, $parameter, '', true);

            if (++$counter > $this->maxParameterReferences) {
                throw new \LogicException("Exceeded the maximum number of parameter matches ('$originalValue')");
            }
        }

        return [$errors, $references];
    }

    /**
     * @param string $value
     * @param array $options
     * @return SyringeError[]
     */
    public function checkConstantReferences(string $value, array $options = []): array
    {
        if (!empty($options["skipConstants"])) {
            return [];
        }

        $errors = [];

        while (($constant = $this->referenceHelper->findNextConstant($value)) !== null) {

            // if this is a class constant, check the class exists
            $classError = false;
            $exploded = explode("::", $constant);
            if (count($exploded) == 2) {
                $className = $exploded[0];
                if (!class_exists($className) && !interface_exists($className)) {
                    $errors[] = $this->errorHelper->referenceError(
                        "The class '$className' for constant '{$exploded[1]}' does not exist"
                    );
                    $classError = true;
                }
            }

            if (!$classError && !defined($constant)) {
                $errors[] = $this->errorHelper->referenceError("The constant '$constant' does not exist");
            }
            $value = $this->referenceHelper->replaceConstantReference($value, $constant, '', true);
        }

        return $errors;
    }

    /**
     * @param string $value
     * @param array $options
     * @return bool|string
     */
    public function checkServiceReference(string $value, array $options = []): string|bool
    {
        if (empty($options["skipServices"]) && $this->referenceHelper->isServiceReference($value) ) {
            $service = $this->referenceHelper->getServiceKey($value);
            if (empty($this->definitions["services"][$service])) {
                return false;
            }
            return $service;
        }
        return true;
    }

    /**
     * @param array $references
     * @param string $key
     * @param string|array $reference
     * @return array
     */
    public function addReference(array $references, string $key, string|array $reference): array
    {
        if (!is_array($reference)) {
            $reference = [$reference];
        }
        $references[$key] = array_merge($references[$key] ?? [], $reference);
        return $references;
    }

    /**.
     * @param array $primaryReferences
     * @param array $secondaryReferences
     * @param string $service
     * @param array $referenceList
     * @return bool
     */
    public function findCircularReferences(
        string $service,
        array $primaryReferences,
        array $secondaryReferences = [],
        array $referenceList = []
    ): bool {
        $referenceList[] = $service;
        foreach ($primaryReferences[$service] ?? $secondaryReferences[$service] ?? [] as $reference) {
            foreach ($referenceList as $previousReference) {
                if (in_array(
                    $previousReference,
                    $primaryReferences[$reference] ?? $secondaryReferences[$reference] ?? [])
                ) {
                    return true;
                }
            }
            if ($this->findCircularReferences($reference, $primaryReferences, $secondaryReferences, $referenceList)) {
                return true;
            }
        }
        return false;
    }

}