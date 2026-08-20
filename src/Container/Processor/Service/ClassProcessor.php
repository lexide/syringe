<?php

namespace Lexide\Syringe\Container\Processor\Service;

class ClassProcessor
{

    /**
     * @param array $definition
     * @return string
     */
    public function process(array $definition): string
    {
        return $definition["class"];
    }

}