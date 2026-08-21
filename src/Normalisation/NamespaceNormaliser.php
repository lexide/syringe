<?php

namespace Lexide\Syringe\Normalisation;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Reference\Reference;
use Lexide\Syringe\Reference\ReferenceHelper;

class NamespaceNormaliser
{

    protected ReferenceHelper $referenceHelper;
    protected ErrorHelper $errorHelper;

    /**
     * @param ReferenceHelper $referenceHelper
     * @param ErrorHelper $errorHelper
     */
    public function __construct(ReferenceHelper $referenceHelper, ErrorHelper $errorHelper)
    {
        $this->referenceHelper = $referenceHelper;
        $this->errorHelper = $errorHelper;
    }

    /**
     * @param array $namespaceDefinitions
     * @return array
     */
    public function normalise(array $namespaceDefinitions): array
    {
        $errors = [];
        $normalisedDefinitions = ["parameters" => [], "services" => [], "extensions" => [], "assertions" => []];
        $namespaces = array_keys($namespaceDefinitions);

        foreach ($namespaceDefinitions as $namespace => $definitions) {
            foreach ($definitions["services"] ?? [] as $key => $serviceDefinition) {

                $serviceDefinition = $this->normaliseArray($serviceDefinition, $namespaces, $namespace, true);

                $key = $this->normaliseNamespacedKey($key, $namespaces, $namespace);

                $storeDefinition = true;

                // handle service key collisions
                if (isset($normalisedDefinitions["services"][$key])) {
                    $storeDefinition = false;
                    // if the definition has not already been aliased
                    if (isset($serviceDefinition["aliasOf"])) {
                        $storeDefinition = true;
                        // if we already have an alias for this key, analyse it to see if it's "local"
                        if (
                            isset($normalisedDefinitions["services"][$key]["aliasOf"]) &&
                            !empty($namespace) // not the root namespace
                        ) {
                            $existingAliasNamespace = $this->getNamespaceFromKey(
                                $this->referenceHelper->getServiceKey($normalisedDefinitions["services"][$key]["aliasOf"]),
                            );
                            $thisAliasNamespace = $this->getNamespaceFromKey(
                                $this->referenceHelper->getServiceKey($serviceDefinition["aliasOf"]),
                            );
                            $thisKeyNamespace = $this->getNamespaceFromKey($key);

                            if (
                                $existingAliasNamespace != $namespace &&
                                $thisAliasNamespace == $thisKeyNamespace
                            ) {
                                // ignore this definition
                                $storeDefinition = false;
                            }
                        }

                    } elseif (!isset($normalisedDefinitions["services"][$key]["aliasOf"])) {
                        // key collision
                        $reportNamespace = empty($namespace)? "root": "'$namespace'";
                        $errors[] = $this->errorHelper->normalisationError("The service '$key' has a definition in the $reportNamespace namespace, but has already been defined");
                    }
                }

                if ($storeDefinition) {
                    $normalisedDefinitions["services"][$key] = $serviceDefinition;
                }
            }

            // namespace parameter keys
            $newParameters = [];
            foreach ($definitions["parameters"] ?? [] as $parameter => $value) {
                $namespacedParameter = $this->normaliseNamespacedKey($parameter, $namespaces, $namespace);

                if (
                    !empty($namespace) &&
                    isset($normalisedDefinitions["parameters"][$namespacedParameter]) &&
                    $this->getNamespaceFromKey($namespacedParameter) == $namespace
                ) {
                    // we have a key collision and the key is local to this namespace
                    // externally set keys take precedence
                    break;
                }

                $newParameters[$namespacedParameter] = $value;
            }

            // namespace parameter values
            // TODO: can this be shortened to remove the array_replace()?
            $normalisedDefinitions["parameters"] = array_replace(
                $normalisedDefinitions["parameters"],
                $this->normaliseArray($newParameters, $namespaces, $namespace, false)
            );

            foreach ($definitions["extensions"] ?? [] as $service => $extension) {
                $service = $this->normaliseNamespacedKey($service, $namespaces, $namespace);
                foreach ($extension as $index => $extensionDefinition) {
                    $extension[$index] = $this->normaliseArray($extensionDefinition, $namespaces, $namespace, true);
                }
                $normalisedDefinitions["extensions"][$service] = $this->mergeExtension($normalisedDefinitions["extensions"][$service] ?? [], $extension);
            }

            foreach ($definitions["assertions"] ?? [] as $assertionDefinition) {
                $assertionDefinition["reference"] = $this->normaliseNamespacedKey($assertionDefinition["reference"], $namespaces, $namespace);
                $normalisedDefinitions["extensions"][] = $assertionDefinition;
            }
        }

        return [$normalisedDefinitions, $errors];
    }

    /**
     * @param array $firstExtension
     * @param array $secondExtension
     * @return array|array[]
     */
    protected function mergeExtension(array $firstExtension, array $secondExtension): array
    {
        if (empty($firstExtension)) {
            return $secondExtension;
        }

        $extension = ["calls" => [], "tags" => []];
        foreach (array_keys($extension) as $key) {
            $extension[$key] = array_merge($firstExtension[$key] ?? [], $secondExtension[$key] ?? []);
            if (empty($extension[$key])) {
                unset($extension[$key]);
            }
        }
        return $extension;
    }

    /**
     * @param array $array
     * @param array $namespaces
     * @param string $currentNamespace
     * @param bool $checkSchemaKeys
     * @param bool $normaliseKeys
     * @return array
     */
    protected function normaliseArray(
        array $array,
        array $namespaces,
        string $currentNamespace,
        bool $checkSchemaKeys,
        bool $normaliseKeys = true
    ): array {
        foreach ($array as $key => $value) {
            unset($array[$key]);
            $check = $checkSchemaKeys && is_string($key)? $key: "";
            switch ($check) {
                case "calls":
                    $value = $this->normaliseCalls($value, $namespaces, $currentNamespace);
                    break;

                case "arguments":
                    $value = $this->normaliseArray($value, $namespaces, $currentNamespace, false, false);
                    break;

                default:
                    if (is_string($value)) {
                        $value = $this->normaliseString($value, $namespaces, $currentNamespace);
                    } elseif (is_array($value)) {
                        $value = $this->normaliseArray($value, $namespaces, $currentNamespace, false);
                    }
                    if ($normaliseKeys && is_string($key)) {
                        $key = $this->normaliseString($key, $namespaces, $currentNamespace);
                    }
                    break;
            }
            $array[$key] = $value;
        }
        return $array;
    }

    /**
     * @param string $string
     * @param array $namespaces
     * @param string $currentNamespace
     * @return string
     */
    protected function normaliseString(string $string, array $namespaces, string $currentNamespace): string
    {
        if ($this->referenceHelper->isServiceReference($string)) {
            $key = $this->referenceHelper->getServiceKey($string);
            $key = $this->normaliseNamespacedKey($key, $namespaces, $currentNamespace);
            return $this->referenceHelper->getServiceReference($key);
        }
        $offset = 0;
        while(!is_null($parameter = $this->referenceHelper->findNextParameter($string, $offset))) {
            $normalisedParameter = $this->normaliseNamespacedKey($parameter, $namespaces, $currentNamespace);
            if ($normalisedParameter != $parameter) {
                $string = $this->referenceHelper->replaceParameterReference($string, $parameter, $normalisedParameter);
            }
            $offset = strpos($string, $normalisedParameter, $offset) + strlen($normalisedParameter) + 1;
        }
        return $string;
    }

    /**
     * @param array $calls
     * @param array $namespaces
     * @param string $currentNamespace
     * @return array
     */
    protected function normaliseCalls(array $calls, array $namespaces, string $currentNamespace): array
    {
        foreach ($calls as $index => $call) {
            $calls[$index] = $this->normaliseArray($call, $namespaces, $currentNamespace, true, false);
        }
        return $calls;
    }

    /**
     * @param string $key
     * @param array $namespaces
     * @param string $currentNamespace
     * @return string
     */
    protected function normaliseNamespacedKey(string $key, array $namespaces, string $currentNamespace): string
    {
        if (!$this->isKeyNamespaced($key, $namespaces)) {
            $key = $this->addNamespaceToKey($currentNamespace, $key);
        }
        return $key;
    }

    /**
     * @param string $key
     * @param array $namespaces
     * @return bool
     */
    protected function isKeyNamespaced(string $key, array $namespaces): bool
    {
        $namespace = strstr($key, Reference::NAMESPACE_SEPARATOR, true);
        if ($namespace === false) {
            return false;
        }

        return in_array($namespace, $namespaces);
    }

    /**
     * @param string $namespace
     * @param string $key
     * @return string
     */
    protected function addNamespaceToKey(string $namespace, string $key): string
    {
        if (empty($namespace)) {
            return $key;
        }
        return $namespace . Reference::NAMESPACE_SEPARATOR . $key;
    }

    /**
     * @param string $namespacedKey
     * @return string
     */
    protected function getNamespaceFromKey(string $namespacedKey): string
    {
        return explode(Reference::NAMESPACE_SEPARATOR, $namespacedKey)[0];
    }

}