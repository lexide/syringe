<?php

namespace Lexide\Syringe\Container;

use Lexide\Syringe\Exception\ConfigException;
use Pimple\Container;

class ContainerFactory
{

    protected string $containerClass;

    /**
     * @param string $containerClass
     */
    public function __construct(string $containerClass = Container::class)
    {
        $this->containerClass = $containerClass;
    }

    /**
     * @return Container
     * @throws ConfigException
     */
    public function create(): Container
    {
        // check existence
        if (!class_exists($this->containerClass)) {
            throw new ConfigException("The container class '{$this->containerClass}' does not exist");
        }
        // check the class is a container
        if ($this->containerClass != Container::class && !is_subclass_of($this->containerClass, Container::class)) {
            throw new ConfigException("The class '{$this->containerClass}' is not a subclass of '" . Container::class . "'");
        }

        return new $this->containerClass();
    }

}