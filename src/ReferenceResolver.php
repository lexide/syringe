<?php

namespace Lexide\Syringe;

use Lexide\Syringe\Exception\ConfigException;
use Pimple\Container;
use Lexide\Syringe\Exception\ReferenceException;

/**
 * Resolves references to existing container definitions
 */
class ReferenceResolver implements ReferenceResolverInterface
{

    protected $replacedParams = [];

    protected $registeredAliases = [];
    
    protected $privateServices = [];

    /**
     * {@inheritDoc}
     */
    public function setRegisteredAliases(array $aliases)
    {
        // flip so we can use isset() later
        $this->registeredAliases = array_flip($aliases);
    }

    /**
     * {@inheritDoc}
     */
    public function registerPrivateService($hashedName, $actualName)
    {
        $this->privateServices[$actualName] = $hashedName;
    }

    /**
     * {@inheritDoc}
     * @throws ReferenceException
     */
    public function resolveService($arg, Container $container, $alias = "")
    {
        if (!is_string($alias)) {
            $alias = "";
        }
        if (is_string($arg) && $arg[0] == ContainerBuilder::SERVICE_CHAR) {
            $originalName = substr($arg, 1);
            $name = $this->aliasThisKey($originalName, $alias);
            // check if the service exists
            if (!$container->offsetExists($name)) {
                // check the un-aliased name
                $name = $originalName;

                if (!$container->offsetExists($name)) {
                    // check for private services if we have an alias to work with

                    $privateName = $originalName;
                    // alias the name if it wasn't already
                    if (!empty($alias) && strpos($originalName, $alias) === false) {
                        $privateName = $this->aliasThisKey($privateName, $alias);
                    }

                    // accessing private services from an unaliased context should always fail
                    if (
                        empty($alias) ||
                        empty($this->privateServices[$privateName]) || 
                        !$container->offsetExists($this->privateServices[$privateName])
                    ) {
                        // no private service either, or the private service key doesn't exist in the container (WTF?!)
                        throw new ReferenceException(sprintf("Tried to inject the service '%s', but it doesn't exist", $name));
                    }
                    
                    $name = $this->privateServices[$privateName];
                }

            }
            $arg = $container[$name];
        }
        return $arg;
    }

    /**
     * {@inheritDoc}
     * @throws ReferenceException
     */
    public function resolveParameter($arg, Container $container, $alias = "")
    {
        if (is_array($arg)) {
            // check each element and key for parameters
            foreach ($arg as $key => $value) {
                $resolvedKey = $this->resolveParameter($key, $container, $alias);
                $arg[$resolvedKey] = $this->resolveParameter($value, $container, $alias);
                if ($key != $resolvedKey) {
                    unset($arg[$key]);
                }
            }
        }
        if (!is_string($arg)) {
            return $arg;
        }
        if (!is_string($alias)) {
            $alias = "";
        }
        $maxLoops = 100;
        $thisLoops = 0;
        while ($thisLoops < $maxLoops && is_string($arg) && substr_count($arg, ContainerBuilder::PARAMETER_CHAR) > 1) {
            ++$thisLoops;
            // parameters
            $char = ContainerBuilder::PARAMETER_CHAR;
            // find the first parameter in the string
            $start = strpos($arg, $char) + 1;
            $end = strpos($arg, $char, $start);
            $param = substr($arg, $start, $end - $start);

            // alias the param and check if it has already been replaced (circular reference) or is already aliased
            $name = $this->aliasThisKey($param, $alias);
            if (isset($this->replacedParams[$name]) || ($this->keyIsAliased($param) && !$container->offsetExists($name))) {
                if (isset($this->replacedParams[$param])) {
                    throw new ReferenceException("Circular reference found for the key '$param'");
                }
                // use the original param
                $name = $param;
            }
            if (!$container->offsetExists($name)) {
                throw new ReferenceException(sprintf("Tried to inject the parameter '%s' in an argument list, but it doesn't exist", $name));
            }
            if (strlen($arg) > strlen($param) + 2) {
                // string replacement
                $arg = str_replace($char . $param . $char, $container[$name], $arg);

            } else {
                // value replacement
                $arg = $container[$name];
            }
            // add param name to the replacement list
            $this->replacedParams[$name] = true;
        }
        if ($thisLoops >= $maxLoops) {
            throw new ReferenceException("Could not resolve parameter '$arg'. The maximum recursion limit was exceeded");
        }
        $this->replacedParams = [];

        // After the parameters have been resolved, check to see if we're trying to resolve a constant, and if so, resolve it
        if (is_string($arg)) {
            if (strpos($arg, ContainerBuilder::CONSTANT_CHAR) === 0 && strrpos($arg, ContainerBuilder::CONSTANT_CHAR) == (strlen($arg) - 1)) {
                $constantRef = substr($arg, 1, -1);

                if (strpos($constantRef, "::") !== false) {
                    $exploded = explode("::", $constantRef);
                    $className = $exploded[0];
                    if (!class_exists($className) && !interface_exists($className)) {
                        throw new ReferenceException("Referenced class '{$className}' doesn't exist");
                    }
                }

                if (!defined($constantRef)) {
                    throw new ReferenceException("Referenced constant '{$constantRef}' doesn't exist");
                }

                $arg = constant($constantRef);
            }
        }

        return $arg;
    }

    public function resolveTag($tag, Container $container)
    {
        if (!is_string($tag) || $tag == "" || $tag[0] != ContainerBuilder::TAG_CHAR) {
            return $tag;
        }

        if (!isset($container[$tag])) {
            return [];
        }

        $collection = $container[$tag];
        if (!$collection instanceof TagCollection) {
            throw new ReferenceException("Could not resolve the tag collection for '$tag'. The collection was invalid");
        }

        $services = [];
        foreach ($collection->getServices() as $key => $serviceName) {
            $services[$key] = $container[$serviceName];
        }

        return $services;
    }

    /**
     * {@inheritDoc}
     */
    public function aliasThisKey($key, $alias)
    {
        if (empty($alias)) {
            return $key;
        }
        if (!is_string($alias)) {
            throw new ConfigException("Alias must be a string");
        }
        return $alias . "." . $key;
    }

    /**
     * {@inheritDoc}
     */
    public function keyIsAliased($key)
    {
        $dot = strpos($key, ".");
        if ($dot === false) {
            // if we don't have a period character, it can't be aliased
            return false;
        }
        $alias = substr($key, 0, $dot);

        // check if the "alias" is registered
        return isset($this->registeredAliases[$alias]);

    }

} 
