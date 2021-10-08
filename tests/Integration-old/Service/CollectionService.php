<?php

namespace Lexide\Syringe\Test\Integration\Service;

class CollectionService
{

    public $services;

    public function __construct(array $services)
    {
        $this->services = $services;
    }

}
