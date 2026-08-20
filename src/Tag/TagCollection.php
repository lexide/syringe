<?php

namespace Lexide\Syringe\Tag;

class TagCollection
{
    protected $services = [];

    /**
     * @param $serviceName
     * @param array $context
     */
    public function addService($serviceName, array $context): void
    {
        $this->services[$serviceName] = $context;
    }

    /**
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

}
