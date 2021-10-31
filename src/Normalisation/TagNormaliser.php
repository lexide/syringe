<?php

namespace Lexide\Syringe\Normalisation;

class TagNormaliser
{

    /**
     * @param array $definitions
     * @return array
     */
    public function normalise(array $definitions): array
    {
        foreach ($definitions["services"] ?? [] as $service => $definition) {
            $tags = [];
            foreach ($definition["tags"] ?? [] as $index => $value) {
                if (is_string($index)) {
                    // old style named format
                    $tag = ["tag" => $index];
                    if (is_string($value)) {
                        $tag["name"] = $value;
                    } else {
                        $tag["order"] = $value;
                    }
                    $tags[] = $tag;
                } elseif (is_string($value)) {
                    // simple format
                    $tags[] = ["tag" => $value];
                } else {
                    // already formatted
                    $tags[] = $value;
                }
            }
            $definition["tags"] = $tags;
            $definitions["services"][$service] = $definition;
        }
        return $definitions;
    }

}