<?php

namespace Lexide\Syringe\Normalisation;

class CallArgumentsNormaliser
{

    /**
     * @param array $definitions
     * @return array
     */
    public function normalise(array $definitions): array
    {
        foreach ($definitions["services"] ?? [] as $reference => $service) {
            foreach ($service["calls"] ?? [] as $i => $call) {
                // ensure all calls have an arguments array
                $call["arguments"] ??= [];
                $service["calls"][$i] = $call;
            }
            $definitions["services"][$reference] = $service;
        }
        return $definitions;
    }

}