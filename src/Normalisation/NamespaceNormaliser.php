<?php

namespace Lexide\Syringe\Normalisation;

use Lexide\Syringe\Compiler\CompilationHelper;
use Lexide\Syringe\ContainerBuilder;
use Lexide\Syringe\Exception\ReferenceException;

class NamespaceNormaliser
{

    /**
     * @var CompilationHelper
     */
    protected $helper;

    /**
     * @param CompilationHelper $helper
     */
    public function __construct(CompilationHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @param array $namespaceDefinitions
     * @return array
     * @throws ReferenceException
     */
    public function normalise(array $namespaceDefinitions): array
    {
        $errors = [];
        $normalisedDefinitions = ["parameters" => [], "services" => [], "extensions" => []];
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
                                $this->helper->getServiceKey($normalisedDefinitions["services"][$key]["aliasOf"]),
                                $namespaces
                            );
                            $thisAliasNamespace = $this->getNamespaceFromKey(
                                $this->helper->getServiceKey($serviceDefinition["aliasOf"]),
                                $namespaces
                            );
                            $thisKeyNamespace = $this->getNamespaceFromKey($key, $namespaces);

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
                        $errors[] = $this->helper->normalisationError("The service '$key' has a definition in the $reportNamespace namespace, but has already been defined");
                    }
                }

                if ($storeDefinition) {
                    $normalisedDefinitions["services"][$key] = $serviceDefinition;
                }
            }

            // namespace parameter keys
            foreach ($definitions["parameters"] ?? [] as $parameter => $value) {
                $namespacedParameter = $this->normaliseNamespacedKey($parameter, $namespaces, $namespace);

                if (
                    isset($normalisedDefinitions["parameters"][$namespacedParameter]) &&
                    $this->getNamespaceFromKey($namespacedParameter, $namespaces) == $namespace
                ) {
                    // we have a key collision and the key is local to this namespace
                    // externally set keys take precedence
                    break;
                }

                $normalisedDefinitions["parameters"][$namespacedParameter] = $value;
            }

            // namespace parameter values
            // TODO: can this be shortened to remove the array_replace()?
            $normalisedDefinitions["parameters"] = array_replace(
                $normalisedDefinitions["parameters"],
                $this->normaliseArray($normalisedDefinitions["parameters"], $namespaces, $namespace, false)
            );

            foreach ($definitions["extensions"] ?? [] as $service => $extension) {
                $service = $this->normaliseNamespacedKey($service, $namespaces, $namespace);
                foreach ($extension as $index => $extensionDefinition) {
                    $extension[$index] = $this->normaliseArray($extensionDefinition, $namespaces, $namespace, true);
                }
                $normalisedDefinitions["extensions"][$service] = $this->mergeExtension($normalisedDefinitions["extensions"][$service] ?? [], $extension);
            }
        }

        return [$normalisedDefinitions, $errors];
    }

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

    protected function normaliseString(string $string, array $namespaces, string $currentNamespace): string
    {
        if ($this->helper->isServiceReference($string)) {
            $key = $this->helper->getServiceKey($string);
            $key = $this->normaliseNamespacedKey($key, $namespaces, $currentNamespace);
            return $this->helper->getServiceReference($key);
        }
        $offset = 0;
        while(!is_null($parameter = $this->helper->findNextParameter($string, $offset))) {
            $normalisedParameter = $this->normaliseNamespacedKey($parameter, $namespaces, $currentNamespace);
            if ($normalisedParameter != $parameter) {
                $string = $this->helper->replaceParameterReference($string, $parameter, $normalisedParameter);
            }
            $offset = strpos($string, $normalisedParameter, $offset) + strlen($normalisedParameter) + 1;
        }
        return $string;
    }

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
        $namespace = strstr($key, ContainerBuilder::NAMESPACE_SEPARATOR, true);
        if ($namespace === false) {
            return false;
        }

        return in_array($namespace, $namespaces);
    }

    protected function addNamespaceToKey(string $namespace, string $key): string
    {
        if (empty($namespace)) {
            return $key;
        }
        return $namespace . ContainerBuilder::NAMESPACE_SEPARATOR . $key;
    }

    protected function getNamespaceFromKey(string $namespacedKey, array $namespaces): string
    {
        $separatorPos = strpos($namespacedKey, ContainerBuilder::NAMESPACE_SEPARATOR);
        if ($separatorPos === false) {
            throw new ReferenceException("Can't get namespace. No separator found in key '$namespacedKey'");
        }

        $namespace = substr($namespacedKey, 0, $separatorPos);
        if (!in_array($namespace, $namespaces)) {
            throw new ReferenceException("Can't get namespace. The key '$namespacedKey' was not prefixed with a registered namespace");
        }

        return $namespace;
    }

}