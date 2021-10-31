<?php

namespace Lexide\Syringe\Schema;

class SchemaLinter
{

    /**
     * @var mixed
     */
    protected $schemas;

    /**
     * @var string[]
     */
    protected $directiveList = [
        "type",
        "children",
        "element",
        "requiredChildren",
        "empty",
        "warning",
        "oneOf"
    ];

    protected $allowedTypes = [
        "string",
        "number",
        "bool",
        "list", // numeric keys
        "array", // mixed numeric and string keys
        "object", // string keys
        "serviceReference",
        "any"
    ];

    /**
     * @param array $schemas
     * @return SchemaLintError[]
     */
    public function lint(array $schemas): array
    {
        if (empty($schemas["schemas"])) {
            return [new SchemaLintError("No 'schemas' attribute was found")];
        }

        $errors = [];
        $this->schemas = $schemas["schemas"];

        foreach ($this->schemas as $schemaName => $schemaDefinition) {
            $errors = array_merge($errors, $this->lintSchema($schemaName, $schemaDefinition));
        }

        return $errors;
    }

    /**
     * @param string $name
     * @param array $schema
     * @return SchemaLintError[]
     */
    protected function lintSchema(string $name, array $schema): array
    {
        if (empty($schema)) {
            return [new SchemaLintError("The schema for %s is empty", [$name])];
        }

        $errors = [];
        if (empty($schema["type"]) && empty($schema["oneOf"])) {
            $errors[] = new SchemaLintError("The schema for %s requires a 'type' or 'oneOf' directive", [$name]);
        }

        // check each directive is correctly formed
        foreach ($schema as $directive => $value) {
            switch ($directive) {
                case "type":
                    if ($this->isNotStringOrStringList($value)) {
                        $errors[] = new SchemaLintError("The %s directive for the %s schema is not a string or list of strings", [$directive, $name]);
                        break;
                    }
                    if (is_string($value)) {
                        $value = [$value];
                    }
                    // loop over the types and check they exist and are allowed for this directive
                    foreach ($value as $type) {
                        if (mb_strpos($type, "@") === 0) {
                            $schemaName = mb_substr($type, 1);
                            if (!isset($this->schemas[$schemaName])) {
                                $errors[] = new SchemaLintError("The %s directive for the %s schema refers to the %s schema which doesn't exist", [$directive, $name, $schemaName]);
                            }
                        } elseif (!in_array($type, $this->allowedTypes)) {
                            $errors[] = new SchemaLintError("The value '%s' for the %s directive for the %s schema is not a valid type", [$type, $directive, $name]);
                        }
                    }
                    break;

                case "children":
                    $error = false;
                    if (is_array($value)) {
                        if (empty($value)) {
                            $errors[] = new SchemaLintError("The %s directive for the %s schema cannot be empty", [$directive, $name]);
                        }
                        // check each element is a valid schema
                        foreach ($value as $attribute => $subSchema) {
                            if (is_int($attribute)) {
                                // error - has numeric keys
                                $error = true;
                            }
                            if ($this->isNotSchema($subSchema)) {
                                $errors[] = new SchemaLintError("The definition for the child %s of the %s schema is not a schema", [$attribute, $name]);
                            } else {
                                $errors = array_merge($errors, $this->lintSchema("$name.$attribute", $subSchema));
                            }
                        }
                    } else {
                        // error - not an array
                        $error = true;
                    }
                    if ($error) {
                        $errors[] = new SchemaLintError("The %s directive for the %s schema is not an array or contains numeric keys", [$directive, $name]);
                    }
                    break;

                case "element":
                    if ($this->isNotSchema($value)) {
                        $errors[] = new SchemaLintError("The %s directive for the %s schema is not a schema", [$directive, $name]);
                        break;
                    }
                    $errors = array_merge($errors, $this->lintSchema($name . ".element", $value));
                    break;

                case "requiredChildren":
                    $error = false;
                    if (is_array($value)) {
                        if (empty($value)) {
                            $errors[] = new SchemaLintError("The %s directive for the %s schema cannot be empty", [$directive, $name]);
                            break;
                        }
                        if (empty($schema["children"])) {
                            $errors[] = new SchemaLintError("The %s directive for the %s schema is set but the %s directive is empty or doesn't exist", [$directive, $name, "children"]);
                        } else {
                            $error = false;
                            // identify badly formed requirements
                            foreach ($value as $attribute => $requirements) {
                                if (is_int($attribute)) {
                                    // Error - has numeric keys
                                    $error = true;
                                    continue;
                                }

                                if (!isset($schema["children"][$attribute])) {
                                    $errors[] = new SchemaLintError("The required child %s of the %s schema is not defined in the %s directive", [$attribute, $name, "children"]);
                                }

                                if ($requirements === true) {
                                    // requirement = child exists
                                    break;
                                }

                                if (!is_array($requirements)) {
                                    $errors[] = new SchemaLintError("The requirements for the required child %s of the %s schema are not boolean true or an array", [$attribute, $name]);
                                    break;
                                }

                                foreach ($requirements as $requirement => $dependencies) {
                                    switch ($requirement) {
                                        case "if":
                                        case "ifNot":
                                            if ($this->isNotStringOrStringList($dependencies)) {
                                                $errors[] = new SchemaLintError("The %s requirements for the required child %s of the %s schema is not a string or list of strings", [$requirement, $attribute, $name]);
                                                break;
                                            }
                                            if (is_string($dependencies)) {
                                                $dependencies = [$dependencies];
                                            }
                                            // ensure the list contains valid children
                                            foreach ($dependencies as $dependency) {
                                                if (!isset($schema["children"][$dependency])) {
                                                    $errors[] = new SchemaLintError("The requirements for required child %s of the %s schema refer to the child %s which is not defined in the %s directive", [$attribute, $name, $dependency, "children"]);
                                                }
                                            }
                                            break;
                                        default:
                                            $errors[] = new SchemaLintError("Unexpected requirement %s for required child %s of the %s schema", [$requirement, $attribute, $name]);
                                    }
                                }
                            }
                        }
                    } else {
                        // Error - not an array
                        $error = true;
                    }

                    if ($error) {
                        $errors[] = new SchemaLintError("The %s directive for the %s schema is not an array or contains numeric keys", [$directive, $name]);
                    }
                    break;

                case "empty":
                    if (!is_bool($value)) {
                        $errors[] = new SchemaLintError("The %s directive for the %s schema is not a boolean", [$directive, $name]);
                    }
                    break;

                case "warning":
                    if (!is_string($value)) {
                        $errors[] = new SchemaLintError("The %s directive for the %s schema is not a string", [$directive, $name]);
                    }
                    break;

                case "oneOf":
                    $error = false;
                    if (is_array($value)) {
                        // ensure each value is a schema
                        foreach ($value as $possibleSchema) {
                            if ($this->isNotSchema($possibleSchema) || empty($possibleSchema["type"])) {
                                // error - value is not a oneOf schema
                                $error = true;
                                break;
                            }
                        }
                    } else {
                        // error - not an array
                        $error = true;
                    }

                    if ($error) {
                        $errors[] = new SchemaLintError("The %s directive for the %s schema is not a list of possible schemas", [$directive, $name]);
                        break;
                    }

                    // lint the schemas in this array
                    foreach ($value as $i => $possibleSchema) {
                        $errors = array_merge($errors, $this->lintSchema($name . ".oneOf[$i]", $possibleSchema));
                    }
                    break;

                default:
                    $errors[] = new SchemaLintError("Unexpected directive %s for the %s schema", [$directive, $name]);
                    break;

            }
        }

        return $errors;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isNotStringOrStringList($value): bool
    {
        $isError = true;
        if (is_string($value)) {
            $isError = false;

        } elseif (is_array($value)) {
            if (empty(array_filter(
                $value,
                function ($type) {
                    return !is_string($type);
                }
            ))) {
                // all values are strings
                $isError = false;
            }

        }
        return $isError;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isNotSchema($value): bool
    {
        $isNotSchema = false;
        if (is_array($value) && !empty($value)) {
            // all keys of the array must be a valid directive
            foreach (array_keys($value) as $key) {
                if (!in_array($key, $this->directiveList)) {
                    $isNotSchema = true;
                }
            }
        } else {
            // empty or not an array
            $isNotSchema = true;
        }
        return $isNotSchema;
    }

}