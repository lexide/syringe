<?php

namespace Lexide\Syringe\Validation;

use Lexide\Syringe\Compiler\CompilationHelper;

class SyntaxValidator
{

    /**
     * @var CompilationHelper
     */
    protected $compilationHelper;

    /**
     * @var array
     */
    protected $schemas;

    /**
     * @param CompilationHelper $compilationHelper
     * @param array $schemas
     */
    public function __construct(CompilationHelper $compilationHelper, array $schemas)
    {
        $this->compilationHelper = $compilationHelper;
        $this->schemas = $schemas;
    }

    /**
     * @param array $definition
     * @param string $fileName
     * @return array
     */
    public function validateFile(array $definition, string $fileName): array
    {
        return $this->validateSchemaByName($definition, "syringe", $fileName);
    }

    /**
     * @param array $definition
     * @param string $schemaName
     * @param string $fileName
     * @param string $elementPath
     * @return array
     */
    protected function validateSchemaByName(
        array $definition,
        string $schemaName,
        string $fileName,
        string $elementPath = ''
    ): array {
        $schema = $this->schemas[$schemaName];
        return $this->validateSchema($definition, $schema, $fileName, $elementPath);
    }

    /**
     * @param mixed $definition
     * @param array $schema
     * @param string $fileName
     * @param string $elementPath
     * @return array
     */
    protected function validateSchema(
        $definition,
        array $schema,
        string $fileName,
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
                                $this->validateSchemaByName($definition, mb_substr($type, 1), $fileName, $elementPath)
                            );
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

                    $errors[] = $this->syntaxError("The type for '$elementPath' is not '$valueText'", $fileName);
                    break;

                // validate the definition's children
                case "children":
                    if (!is_array($definition)) {
                        $errors[] = $this->syntaxError("'$elementPath' is not an object", $fileName);
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
                                    $fileName,
                                    "$elementPath.$child"
                                )
                            );
                        }
                        unset($childList[$child]);
                    }

                    if (!empty($childList)) {
                        $errors[] = $this->syntaxError(
                            "'$elementPath' contains child elements that are not allowed: '"
                                . implode("', '", $childList) . "'",
                            $fileName
                        );
                    }

                    break;

                // validate each element in the definition list
                case "element":
                    if (!is_array($definition)) {
                        $errors[] = $this->syntaxError("'$elementPath' is not an array", $fileName);
                        break;
                    }

                    foreach ($definition as $i => $element) {
                        // validate each element in the definition list
                        $errors = array_merge(
                            $errors,
                            $this->validateSchema($element, $directive, $fileName, "$elementPath.$i")
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
                            $errors[] = $this->syntaxError("The required '$child' attribute of '$elementPath' was missing", $fileName);
                        }
                    }
                    break;

                // validate that the definition is empty or not
                case "empty":
                    if (empty($definition) xor $directive) {
                        $errors[] = $this->syntaxError("'$elementPath' cannot be empty", $fileName);
                    }
                    break;

                // raise warnings if necessary
                case "warning":
                    $errors[] = $this->warning($directive, $fileName);
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
                            $this->validateSchema($definition, $possibleSchema, $fileName, $elementPath)
                        );
                        break;
                    }

                    if (!$matchedType) {
                        $errors[] = $this->syntaxError("The definition for '$elementPath' is invalid", $fileName);
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
    protected function checkType(string $type, $definition): bool
    {
        if ($type == "any") {
            return true;
        }

        // scalar types
        $defType = gettype($definition);

        // normalise PHP types to syringe types
        if (in_array($defType, ["int", "integer", "float", "double"])) {
            $defType = "number";
        } elseif ($defType == "boolean") {
            $defType = "bool";
        }

        // do a direct match for scalar types
        if (in_array($type, ['string', 'bool', 'number']) && $type == $defType) {
            return true;
        }

        if ($type == "serviceReference") {
            return is_string($definition) && $this->compilationHelper->isServiceReference($definition);
        }

        // PHP arrays can be "list", "array" or "object" types
        if ($defType == "array") {
            if ($type == "array") {
                // "array" allows mixed keys so no need to check further
                return true;
            }

            $hasNumericKeys = false;
            $hasAssociativeKeys = false;
            foreach (array_keys($definition) as $key) {
                $hasNumericKeys = $hasNumericKeys || is_int($key);
                $hasAssociativeKeys = $hasAssociativeKeys || is_string($key);
            }

            // check we don't have both assoc and numeric keys, then check the key types for list and object
            return !($hasAssociativeKeys && $hasNumericKeys) && (
                ($type == "list" && $hasNumericKeys) ||
                ($type == "object" && $hasAssociativeKeys)
            );

        }

        return false;

    }

    /**
     * @param string|string[] $value
     * @return string[]
     */
    protected function normaliseToArray($value): array
    {
        if (is_scalar($value)) {
            $value = [$value];
        }
        return $value;
    }

    /**
     * @param string $message
     * @param string $fileName
     * @return ValidationError
     */
    protected function syntaxError(string $message, string $fileName): ValidationError
    {
        return $this->compilationHelper->syntaxError($message, ["filename" => $fileName]);
    }

    /**
     * @param string $warning
     * @param string $fileName
     * @return ValidationError
     */
    protected function warning(string $warning, string $fileName): ValidationError
    {
        return $this->compilationHelper->warning($warning, ["filename" => $fileName]);
    }

}