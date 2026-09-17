<?php

namespace Lexide\Syringe\Validation;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Reference\ReferenceHelper;

class ReferenceValidator
{

    protected ReferenceValidatorHelper $validatorHelper;
    protected ReferenceHelper $referenceHelper;
    protected ErrorHelper $errorHelper;

    /**
     * @param ReferenceValidatorHelper $validatorHelper
     * @param ReferenceHelper $referenceHelper
     * @param ErrorHelper $errorHelper
     */
    public function __construct(ReferenceValidatorHelper $validatorHelper, ReferenceHelper $referenceHelper, ErrorHelper $errorHelper)
    {
        $this->validatorHelper = $validatorHelper;
        $this->referenceHelper = $referenceHelper;
        $this->errorHelper = $errorHelper;
    }

    /**
     * @param array $definitions
     * @return array
     */
    public function validate(array $definitions): array
    {
        $this->validatorHelper->setDefinitions($definitions);
        return array_merge(
            $this->validateParameters($definitions),
            $this->validateServices($definitions)
        );
    }

    /**
     * @param array $definitions
     * @return array
     */
    protected function validateParameters(array $definitions): array
    {
        if (empty($definitions["parameters"])) {
            return [];
        }
        if (!is_array($definitions["parameters"])) {
            return [$this->errorHelper->referenceError("The value for 'parameters' is not an array")];
        }

        $errors = [];

        $parameterReferences = [];
        foreach ($definitions["parameters"] as $parameter => $value) {
            [$parameterErrors, $references] = $this->validatorHelper->checkValueForReferences(
                $value,
                ["skipServices" => true]
            );
            $parameterReferences = $this->validatorHelper->addReference($parameterReferences, $parameter, $references);
            foreach ($parameterErrors as $error) {
                $error->addContext("parameter", $parameter);
            }
            $errors = array_merge($errors, $parameterErrors);
        }

        // add any errors regarding circular references
        foreach (array_keys($parameterReferences) as $parameter) {
            if ($this->validatorHelper->findCircularReferences($parameter, $parameterReferences)) {
                $circularReferenceError = $this->errorHelper->referenceError(
                    "A circular reference was found for the parameter '$parameter'"
                );
                $circularReferenceError->addContext("parameter", $parameter);
                $errors[] = $circularReferenceError;
            }
        }

        return $errors;
    }

    /**
     * @param array $definitions
     * @return array
     */
    protected function validateServices(array $definitions): array
    {
        if (empty($definitions["services"])) {
            return [];
        }
        if (!is_array($definitions["services"])) {
            return [$this->errorHelper->referenceError("The value for 'services' is not an array")];
        }

        $errors = [];
        $serviceReferences = [];
        $tagReferences = [];
        foreach ($definitions["services"] as $service => $definition) {

            if (!is_array($definition)) {
                $serviceError = $this->errorHelper->referenceError(
                    "The service definition for '$service' was not an array"
                );
                $serviceError->addContext("service", $service);
                $errors[] = $serviceError;
                continue;
            }

            $serviceErrors = [];

            $classExists = false;
            if (!empty($definition["class"])) {
                if (!class_exists($definition["class"])) {
                    if (!empty($definition["stub"])) {
                        $serviceErrors[] = $this->errorHelper->warning(
                            "Stub services that define a 'class' are deprecated"
                        );
                    } else {
                        $serviceErrors[] = $this->errorHelper->referenceError(
                            "The class {$definition["class"]} does not exist"
                        );
                    }
                } else {
                    $classExists = true;
                }
            }

            if (!empty($definition["arguments"])) {
                [$argumentErrors, $argumentReferences] = $this->validatorHelper->checkArrayForReferences(
                    $definition["arguments"]
                );
                $serviceErrors = array_merge($serviceErrors, $argumentErrors);
                $serviceReferences = $this->validatorHelper->addReference(
                    $serviceReferences,
                    $service,
                    $argumentReferences
                );
            }

            // check factory definition is correct
            $factoryClass = null;
            $needsStaticFactoryMethod = false;

            if (!empty($definition["factoryService"])) {
                $factoryService = $definition["factoryService"];
                $serviceKey = $this->referenceHelper->getServiceKey($factoryService);

                if ($this->validatorHelper->checkServiceReference($factoryService) === false) {
                    $serviceErrors[] = $this->errorHelper->referenceError(
                        "The factory service '$serviceKey' does not exist"
                    );
                } else {
                    $serviceReferences = $this->validatorHelper->addReference(
                        $serviceReferences,
                        $service,
                        $serviceKey
                    );

                    // save the factory class, so we can check the method later
                    $factoryClass = $definitions["services"][$serviceKey]["class"];
                }
            }

            if (!empty($definition["factoryClass"])) {
                if (!empty($definition["factoryService"])) {
                    $serviceErrors[] = $this->errorHelper->referenceError(
                        "Cannot use both factoryService and factoryClass directives in the same service definition"
                    );
                    unset($factoryClass); // don't check the factory method in this scenario

                } elseif (!class_exists($definition["factoryClass"])) {
                    $serviceErrors[] = $this->errorHelper->referenceError(
                        "The factory class '{$definition["factoryClass"]}' does not exist"
                    );

                } else {
                    $factoryClass = $definition["factoryClass"];
                    $needsStaticFactoryMethod = true;
                }
            }

            if (!empty($factoryClass)) {
                if (class_exists($factoryClass)) {
                    // We check the class here in case the factory service definition uses an invalid class
                    // We want to raise the error relative to that definition rather than this one, so no error here

                    $method = $definition["factoryMethod"];
                    if (!method_exists($factoryClass, $method)) {
                        $serviceErrors[] = $this->errorHelper->referenceError(
                            "The factory method '$method' does not exist on the class '$factoryClass'"
                        );
                    } elseif ($needsStaticFactoryMethod) {
                        $factoryMethod = new \ReflectionMethod($factoryClass, $method);
                        if (!$factoryMethod->isStatic()) {
                            $serviceErrors[] = $this->errorHelper->referenceError(
                                "The factory class method '$factoryClass::$method' is not a static method"
                            );
                        }
                    }
                }

            }

            if (!empty($definition["aliasOf"])) {
                $aliasOf = $definition["aliasOf"];
                $serviceKey = $this->referenceHelper->getServiceKey($aliasOf);
                if ($this->validatorHelper->checkServiceReference($aliasOf) === false) {
                    $serviceErrors[] = $this->errorHelper->referenceError(
                        "The alias '$serviceKey' does not exist"
                    );
                } else {
                    $serviceReferences = $this->validatorHelper->addReference(
                        $serviceReferences,
                        $service,
                        $serviceKey
                    );
                }
            }

            if (!empty($definition["calls"])) {
                foreach ($definition["calls"] as $callDefinition) {
                    if ($classExists && !method_exists($definition["class"], $callDefinition["method"])) {
                        $serviceErrors[] = $this->errorHelper->referenceError(
                            "The call method '{$callDefinition["method"]}' " .
                            "does not exist on the service class '{$definition["class"]}'"
                        );
                    }
                    if (!empty($callDefinition["arguments"])) {
                        [$argumentErrors, $argumentReferences] = $this->validatorHelper->checkArrayForReferences(
                            $callDefinition["arguments"]
                        );
                        $serviceErrors = array_merge($serviceErrors, $argumentErrors);
                        $serviceReferences = $this->validatorHelper->addReference(
                            $serviceReferences,
                            $service,
                            $argumentReferences
                        );
                    }
                }
            }

            if (!empty($definition["tags"])) {
                foreach ($definition["tags"] as $tagDefinition) {
                    [$tagErrors] = $this->validatorHelper->checkArrayForReferences(
                        $tagDefinition,
                        ["skipServices" => true]
                    );
                    $serviceErrors = array_merge($serviceErrors, $tagErrors);
                    $tag = $tagDefinition["tag"];
                    $tagReferences = $this->validatorHelper->addReference($tagReferences, $tag, $service);
                }
            }

            foreach ($serviceErrors as $error) {
                $error->addContext("service", $service);
            }

            $errors = array_merge($errors, $serviceErrors);
        }

        // add any errors regarding circular references
        foreach (array_keys($serviceReferences) as $service) {
            if ($this->validatorHelper->findCircularReferences($service, $serviceReferences, $tagReferences)) {
                $circularReferenceError = $this->errorHelper->referenceError(
                    "A circular reference was found for the service '$service'"
                );
                $circularReferenceError->addContext("service", $service);
                $errors[] = $circularReferenceError;
            }
        }

        return $errors;
    }

}