<?php

namespace Lexide\Syringe\Reference;

use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Tag\TagIterator;
use Pimple\Container;

interface ReferenceResolverInterface
{

    /**
     * Resolves $arg to a parameter, service or tag depending on it's value
     *
     * @param mixed $arg
     * @param Container $container
     * @param string $class
     * @param string $method
     * @param int|string $argumentKey
     * @return mixed
     * @throws ReferenceException
     */
    public function resolveArgument(mixed $arg, Container $container, string $class, string $method, int|string $argumentKey): mixed;


    /**
     * Checks if $arg is a service reference and loads it from the container
     *
     * @param mixed $arg
     * @param Container $container
     * @return mixed
     * @throws ReferenceException
     */
    public function resolveService(mixed $arg, Container $container): mixed;

    /**
     * Inserts parameters references into $arg, recursively if required
     *
     * @param mixed $arg
     * @param Container $container
     * @throws ReferenceException
     * @return mixed
     * @throws ReferenceException
     */
    public function resolveParameter(mixed $arg, Container $container): mixed;

    /**
     * Returns an array of services that have been tagged with the specified value
     * Alternatively returns a TagIterator, for lazy service loading, if the method argument is appropriately typed
     *
     * @param mixed $tag
     * @param Container $container
     * @param string $class
     * @param string $method
     * @param int|string $argumentKey
     * @return mixed
     * @throws ReferenceException
     */
    public function resolveTag(mixed $tag, Container $container, string $class, string $method, int|string $argumentKey): mixed;

    /**
     * Obfuscate a service name to make it private, while keeping a record to allow access to other services within
     * the same namespace alias
     *
     * @param string $hashedName - the unique obfuscated service name
     * @param string $actualName - the fully aliased service name
     */
    public function registerPrivateService(string $hashedName, string $actualName): void;

} 
