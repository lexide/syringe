<?php

namespace Lexide\Syringe;

use Lexide\Syringe\Exception\ReferenceException;

class TagCollection
{
    protected $services = [];

    public function addService($serviceName, $key = null)
    {
        if ((is_int($key) && !empty($this->services[$key])) || (empty($key) && $key !== 0)) {
            $key = empty($this->services)
                ? 0
                : array_reduce(array_keys($this->services), function ($result, $value) {
                    if (is_int($value) && $value > $result) {
                        return $value;
                    }
                    return $result;
                }, -1) + 1;
        }
        $this->services[$key] = $serviceName;
    }

    public function getServices()
    {
        return $this->services;
    }

    public function getService($key)
    {
        if (empty($this->services[$key])) {
            throw new ReferenceException("No service with the key '$key' was found in this tag");
        }
        return $this->services[$key];
    }


} 
