<?php

namespace Lexide\Syringe\Validation;

class TypeValidator
{

    /**
     * @param string $type
     * @param mixed $definition
     * @return bool
     */
    public function checkType(string $type, mixed $definition): bool
    {
        if ($type == "any") {
            return true;
        }

        // scalar types
        $defType = gettype($definition);

        // straight type check
        if ($type == strtolower($defType)) {
            return true;
        }

        // normalise PHP types to syringe types
        if (in_array($defType, ["int", "integer", "float", "double"]) && $type == "number") {
            return true;
        } elseif ($defType == "boolean" && $type == "bool") {
            return true;
        }

        // PHP arrays can be "list", "array" or "object" types, though "array" will already have been checked by this point
        if ($defType == "array") {

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

}