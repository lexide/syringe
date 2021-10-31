<?php

namespace Lexide\Syringe\Normalisation;

class ExtensionCallsNormaliser
{

    /**
     * @param array $definitions
     * @return array
     */
    public function normalise(array $definitions): array
    {
        // normalise extensions from [] to [calls:[]]
        // definitions are still namespaced at this point
        foreach ($definitions as $namespace => $definition) {
            foreach ($definition["extensions"] ?? [] as $service => $extensions) {
                if (!isset($extensions["calls"])) {
                    $extensions = ["calls" => $extensions];
                }
                $definition["extensions"][$service] = $extensions;
            }
            $definitions[$namespace] = $definition;
        }
        return $definitions;
    }

}