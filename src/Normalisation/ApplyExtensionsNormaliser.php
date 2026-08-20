<?php

namespace Lexide\Syringe\Normalisation;

use Lexide\Syringe\Error\ErrorHelper;

class ApplyExtensionsNormaliser
{

    protected ErrorHelper $helper;

    /**
     * @param ErrorHelper $helper
     */
    public function __construct(ErrorHelper $helper)
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
        foreach ($definitions["extensions"] ?? [] as $service => $extensions) {
            if (empty($definitions["services"][$service])) {
                $errors[] = $this->helper->normalisationError("An extension was found for '$service' but that service does not exist");
                continue;
            }
            $serviceDefinition = $definitions["services"][$service];
            foreach ($extensions as $key => $values) {
                $serviceDefinition[$key] = array_merge($serviceDefinition[$key] ?? [], $values);
            }
            $definitions["services"][$service] = $serviceDefinition;
        }
        return [$definitions, $errors];
    }

}