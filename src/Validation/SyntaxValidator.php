<?php

namespace Lexide\Syringe\Validation;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Error\SyringeError;
use Lexide\Syringe\Reference\ReferenceHelper;

class SyntaxValidator
{

    protected ReferenceHelper $referenceHelper;
    protected ErrorHelper $errorHelper;
    protected TypeValidator $typeValidator;
    protected array $schemata;

    /**
     * @param ReferenceHelper $referenceHelper
     * @param ErrorHelper $errorHelper
     * @param TypeValidator $typeValidator
     * @param array $schemata
     */
    public function __construct(ReferenceHelper $referenceHelper, ErrorHelper $errorHelper, TypeValidator $typeValidator, array $schemata)
    {
        $this->referenceHelper = $referenceHelper;
        $this->errorHelper = $errorHelper;
        $this->typeValidator = $typeValidator;
        $this->schemata = $schemata;
    }

    /**
     * @param array $definition
     * @param string $source
     * @return array
     */
    public function validate(array $definition, string $source): array
    {
        return $this->validateSchemaByName($definition, "syringe", $source);
    }

    /**
     * @param array $definition
     * @param string $schemaName
     * @param string $source
     * @param string $elementPath
     * @return array
     */
    protected function validateSchemaByName(
        mixed $definition,
        string $schemaName,
        string $source,
        string $elementPath = ''
    ): array {
        $schema = $this->schemata[$schemaName];
        return $this->validateSchema($definition, $schema, $source, $elementPath);
    }

    /**
     * @param mixed $definition
     * @param array $schema
     * @param string $source
     * @param string $elementPath
     * @return array
     */
    protected function validateSchema(
        mixed $definition,
        array $schema,
        string $source,
        string $elementPath = ''
    ): array {
        $errors = [];
        foreach ($schema as $directiveName => $directive) {
            switch ($directiveName) {
                // validate the definition is of the correct type
                case "type":
                    if (is_string($directive)) {
                        $directive = [$directive];
                    }

                    foreach ($directive as $type) {
                        if (mb_strpos($type, "@") === 0) {
                            // schema reference
                            $errors = array_merge(
                                $errors,
                                $this->validateSchemaByName($definition, mb_substr($type, 1), $source, $elementPath)
                            );
                            break 2;
                        } elseif ($this->checkType($type, $definition)) {
                            // type checking passed, we're done with this directive
                            // break the switch
                            break 2;
                        }
                    }

                    // format the value list to insert into the error message
                    $valueText = array_pop($directive);
                    if (!empty($directive)) {
                        $valueText = implode("', '", $directive) . " or '$valueText";
                    }

                    $errors[] = $this->syntaxError("The type for '$elementPath' is not '$valueText'", $source);
                    break;

                // validate the definition's children
                case "children":
                    if (!is_array($definition)) {
                        $errors[] = $this->syntaxError("'$elementPath' is not an object", $source);
                        break;
                    }

                    $childList = array_flip(array_keys($definition));
                    foreach ($directive as $child => $childSchema) {
                        if (isset($definition[$child])) {
                            // validate the child definition
                            $errors = array_merge(
                                $errors,
                                $this->validateSchema(
                                    $definition[$child],
                                    $childSchema,
                                    $source,
                                    implode(">", array_filter([$elementPath, "$child"]))
                                )
                            );
                        }
                        unset($childList[$child]);
                    }

                    if (!empty($childList)) {
                        $errors[] = $this->syntaxError(
                            "'$elementPath' contains child elements that are not allowed: '"
                                . implode("', '", array_keys($childList)) . "'",
                            $source
                        );
                    }

                    break;

                // validate each element in the definition list
                case "element":
                    if (!is_array($definition)) {
                        $errors[] = $this->syntaxError("'$elementPath' is not an array", $source);
                        break;
                    }

                    foreach ($definition as $i => $element) {
                        // validate each element in the definition list
                        $errors = array_merge(
                            $errors,
                            $this->validateSchema($element, $directive, $source, "$elementPath>$i")
                        );
                    }
                    break;

                // validate that the required child definitions exist (if necessary)
                case "requiredChildren":
                    foreach ($directive as $child => $requirement) {
                        $shouldCheck = false;

                        // identify if we need to check if this child definition exists
                        if ($requirement === true) {
                            $shouldCheck = true;

                        } elseif (isset($requirement["if"])) {
                            $if = $this->normaliseToArray($requirement["if"]);
                            foreach ($if as $checkChild) {
                                if(isset($definition[$checkChild])) {
                                    // dependency found, check this child definition
                                    $shouldCheck = true;
                                    break;
                                }
                            }

                        } elseif (isset($requirement["ifNot"])) {
                            $shouldCheck = true;
                            $ifNot = $this->normaliseToArray($requirement["ifNot"]);
                            foreach ($ifNot as $checkChild) {
                                if(isset($definition[$checkChild])) {
                                    // inverse dependency found, no need to check
                                    $shouldCheck = false;
                                    break;
                                }
                            }
                        }

                        if ($shouldCheck && !isset($definition[$child])) {
                            $errors[] = $this->syntaxError("The required '$child' attribute of '$elementPath' was missing", $source);
                        }
                    }
                    break;

                // validate that the definition is empty or not
                case "empty":
                    if (empty($definition) xor $directive) {
                        $errors[] = $this->syntaxError("'$elementPath' " . ($directive ? "must" : "cannot") . " be empty", $source);
                    }
                    break;

                // raise warnings if necessary
                case "warning":
                    $errors[] = $this->warning($directive, $source);
                    break;

                // ensure that the definition is valid against one of the listed schemas
                // these are selected by "type"
                case "oneOf":
                    $matchedType = false;
                    // loop over possible schemas and check if the type matches this definition
                    foreach ($directive as $possibleSchema) {
                        if (!$this->checkType($possibleSchema["type"], $definition)) {
                            continue;
                        }
                        $matchedType = true;
                        // validate the definition according to this schema
                        $errors = array_merge(
                            $errors,
                            $this->validateSchema($definition, $possibleSchema, $source, $elementPath)
                        );
                        break;
                    }

                    if (!$matchedType) {
                        $errors[] = $this->syntaxError("The definition for '$elementPath' is invalid", $source);
                    }
                    break;
            }
        }

        return $errors;
    }

    /**
     * @param string $type
     * @param mixed $definition
     * @return bool
     */
    protected function checkType(string $type, mixed $definition): bool
    {

        if ($this->typeValidator->checkType($type, $definition)) {
            return true;
        }

        if ($type == "serviceReference") {
            return is_string($definition) && $this->referenceHelper->isServiceReference($definition);
        }

        return false;

    }

    /**
     * @param string|string[] $value
     * @return string[]
     */
    protected function normaliseToArray(mixed $value): array
    {
        if (is_scalar($value)) {
            $value = [$value];
        }
        return $value;
    }

    /**
     * @param string $message
     * @param string $source
     * @return SyringeError
     */
    protected function syntaxError(string $message, string $source): SyringeError
    {
        return $this->errorHelper->syntaxError($message, ["source" => $source]);
    }

    /**
     * @param string $warning
     * @param string $source
     * @return SyringeError
     */
    protected function warning(string $warning, string $source): SyringeError
    {
        return $this->errorHelper->warning($warning, ["source" => $source]);
    }

}