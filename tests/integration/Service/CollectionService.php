<?php

namespace Lexide\Syringe\IntegrationTests\Service;

class CollectionService
{

    public $services;

    public function __construct(array $services)
    {
        $this->services = $services;
    }

}
