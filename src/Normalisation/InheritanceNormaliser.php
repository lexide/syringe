<?php

namespace Lexide\Syringe\Normalisation;

use Lexide\Syringe\Compiler\CompilationHelper;

class InheritanceNormaliser
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
     * @param array $definitions
     * @return array
     */
    public function normalise(array $definitions): array
    {
        $errors = [];

        $services = $definitions["services"] ?? [];
        $abstractServices = [];
        // filter abstract services into a separate array
        foreach ($services as $key => $service) {
            if (!empty($service["abstract"])) {
                unset($service["abstract"]);
                $abstractServices[$key] = $service;
                unset($services[$key]);
            }
        }

        // check for inheritance and merge definitions
        foreach ($services as $key => $service) {
            $chain = [];
            while (!empty($service["extends"])) {
                $extends = $service["extends"];
                unset($service["extends"]);
                if (empty($abstractServices[$extends])) {
                    $errors[] = $this->helper->normalisationError(
                        "The service definition for '$key' extends '$extends', which is not an abstract service",
                        ["service" => $key]
                    );
                    break;
                }

                // check for circular inheritance
                if (in_array($extends, $chain)) {
                    $chain[] = $extends;
                    $errors[] = $this->helper->normalisationError(
                        "The service definition for '$key' has circular inheritance",
                        ["service" => $key, "chain" => $chain]
                    );
                    continue 2;
                }

                $chain[] = $extends;

                // save calls and tags so they can be merged instead of replaced
                $calls = $service["calls"] ?? [];
                $tags = $service["tags"] ?? [];

                // merge the definitions together
                $service = array_replace_recursive($abstractServices[$extends], $service);
                $service["calls"] = array_merge($calls, $abstractServices[$extends]["calls"] ?? []);
                $service["tags"] = array_merge($tags, $abstractServices[$extends]["tags"] ?? []);

            }
            $services[$key] = $service;
        }

        $definitions["services"] = $services;

        return [$definitions, $errors];
    }

}