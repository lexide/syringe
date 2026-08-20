<?php

namespace Lexide\Syringe\Reference;

use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Tag\TagCollection;
use Lexide\Syringe\Tag\TagIterator;
use Lexide\Syringe\Tag\TagIteratorFactory;
use Pimple\Container;

/**
 * Resolves references to existing container definitions
 */
class ReferenceResolver implements ReferenceResolverInterface
{

    protected ReferenceHelper $referenceHelper;
    protected TagIteratorFactory $tagIteratorFactory;

    protected array $replacedParams = [];
    protected array $privateServices = [];

    /**
     * @param ReferenceHelper $referenceHelper
     * @param TagIteratorFactory $tagIteratorFactory
     */
    public function __construct(ReferenceHelper $referenceHelper, TagIteratorFactory $tagIteratorFactory)
    {
        $this->referenceHelper = $referenceHelper;
        $this->tagIteratorFactory = $tagIteratorFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function registerPrivateService(string $hashedName, string $actualName): void
    {
        $this->privateServices[$actualName] = $hashedName;
    }

    /**
     * @throws ReferenceException
     */
    public function resolveArgument(mixed $arg, Container $container, string $class, string $method, int|string $argumentKey): mixed
    {
        if (!is_string($arg)) {
            return $arg;
        }

        return match (true) {
            $this->referenceHelper->isTagReference($arg) => $this->resolveTag($arg, $container, $class, $method, $argumentKey),
            $this->referenceHelper->isServiceReference($arg) => $this->resolveService($arg, $container),
            default => $this->resolveParameter($arg, $container),
        };
    }

    /**
     * {@inheritDoc}
     */
    public function resolveService(mixed $arg, Container $container): mixed
    {

        if ($this->referenceHelper->isServiceReference($arg)) {
            $name = $this->referenceHelper->getServiceKey($arg);
            // Check if the service exists
            if (!$container->offsetExists($name)) {
                // Check for private services
                $privateName = $name;

                if (
                    empty($this->privateServices[$privateName]) ||
                    !$container->offsetExists($this->privateServices[$privateName])
                ) {
                    // No private service either, or the private service key doesn't exist in the container
                    throw new ReferenceException(sprintf("Tried to inject the service '%s', but it doesn't exist", $name));
                }

                $name = $this->privateServices[$privateName];
            }
            $arg = $container[$name];
        }
        return $arg;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveParameter(mixed $arg, Container $container): mixed
    {
        // Resolve nested parameters
        if (is_array($arg)) {
            // Check each element and key for parameters
            $newArg = [];
            foreach ($arg as $key => $value) {
                $resolvedKey = $this->resolveParameter($key, $container);
                $newArg[$resolvedKey] = $this->resolveParameter($value, $container);
            }
            $arg = $newArg;
        }

        // No need to check for replacements if we don't have a string
        if (!is_string($arg)) {
            return $arg;
        }

        $maxLoops = 100;
        $thisLoops = 0;
        $char = Reference::PARAMETER_CHAR;

        while ($thisLoops < $maxLoops && is_string($arg) && $name = $this->referenceHelper->findNextParameter($arg)) {
            ++$thisLoops;

            $nameLength = strlen($name);
            $replaceLength = $nameLength + 2;
            $replacementLength = 0;

            $replaceStart = strpos($arg, "$char{$name}$char");
            $replaceEnd = $replaceStart + $replaceLength;

            if (isset($this->replacedParams[$name])) {
                // Check the recorded start and end values to see if this replacement falls within one already seen
                foreach ($this->replacedParams[$name] as [$checkStart, $checkEnd]) {
                    if ($replaceStart >= $checkStart && $replaceEnd <= $checkEnd) {
                        // This indicates that the container was modified after validation, or validation is turned off
                        throw new ReferenceException("Circular reference found for the key '$name'");
                    }
                }
            }

            if (!$container->offsetExists($name)) {
                // This indicates that the container was modified after validation, or validation is turned off
                throw new ReferenceException("Tried to inject the parameter '$name' in an argument list, but it doesn't exist");
            }

            if (strlen($arg) > $replaceLength) {
                // String replacement: "insert %foo% into the string"
                $replacement = $container[$name];
                if (!is_scalar($replacement)) {
                    throw new ReferenceException("Tried to interpolate the parameter '$name' into a parameter but it is not a scalar value");
                }
                $replacement = (string) $replacement;
                $replacementLength = strlen($replacement);
                $arg = $this->referenceHelper->replaceParameterReference($arg, $name, $replacement, true);

            } else {
                // Value replacement: "%foo%"
                $arg = $container[$name];
                if (is_string($arg)) {
                    $replacementLength = strlen($arg);
                }
            }

            // Add param name to the replacement list
            $this->adjustReplacedParams($replaceStart, $nameLength, $replacementLength);
            $this->replacedParams[$name][] = [$replaceStart, $replaceStart + $replacementLength];

        }

        if ($thisLoops >= $maxLoops) {
            throw new ReferenceException("Could not resolve parameter '$arg'. The maximum recursion limit was exceeded");
        }
        $this->replacedParams = [];

        if (is_string($arg)) {
            // Resolve constants
            $thisLoops = 0;
            while ($thisLoops < $maxLoops && is_string($arg) && $constantRef = $this->referenceHelper->findNextConstant($arg)) {
                ++$thisLoops;

                if (str_contains($constantRef, "::")) {
                    $exploded = explode("::", $constantRef, 2);
                    $className = $exploded[0];
                    if (!class_exists($className) && !interface_exists($className)) {
                        throw new ReferenceException("Referenced class '{$className}' doesn't exist");
                    }
                }

                if (!defined($constantRef)) {
                    throw new ReferenceException("Referenced constant '{$constantRef}' doesn't exist");
                }

                $value = constant($constantRef);

                if (strlen($arg) > strlen($constantRef) + 2) {
                    $arg = $this->referenceHelper->replaceConstantReference($arg, $constantRef, $value, true);
                } else {
                    $arg = $value;
                }

            }

            if ($thisLoops >= $maxLoops) {
                throw new ReferenceException("Could not resolve constant '$arg'. The maximum recursion limit was exceeded");
            }

            // unescape any escaped characters
            if (is_string($arg)) {
                $arg = $this->referenceHelper->unescapeCharacters($arg);
            }
        }

        return $arg;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveTag(mixed $tag, Container $container, string $class = "", string $method = "", int|string $argumentKey = 0): mixed
    {
        if (!$this->referenceHelper->isTagReference($tag)) {
            return $tag;
        }

        // Default the collection to null, so we can process an empty iterator if required
        $collection = null;

        if (isset($container[$tag])) {
            $collection = $container[$tag];
            if (!$collection instanceof TagCollection) {
                throw new ReferenceException("Could not resolve the tag collection for '$tag'. The collection was invalid");
            }
        }
        $value = $this->tagIteratorFactory->create($container, $collection);

        $argumentTypes = [];
        if (!empty($class) && !empty($method)) {
            // Get the type hint(s) for this argument
            try {
                $reflectedMethod = new \ReflectionMethod($class, $method);
                $arguments = $reflectedMethod->getParameters();
                if (is_int($argumentKey)) {
                    $tagArgument = $arguments[$argumentKey] ?? null;
                } else {
                    // Handle named arguments
                    foreach ($arguments as $argument) {
                        if ($argument->getName() == $argumentKey) {
                            $tagArgument = $argument;
                            break;
                        }
                    }
                }
                if (empty($tagArgument)) {
                    throw new ReferenceException(
                        "Could not resolve the tag collection for '$tag'. $class::$method does not have argument " .
                        (is_int($argumentKey) ? "#$argumentKey" : "'$argumentKey'")
                    );
                }
            } catch (\ReflectionException $e) {
                throw new ReferenceException(
                    "Could not resolve the tag collection for '$tag'. There was a reflection error for $class::$method",
                    previous: $e
                );
            }

            $argumentTypes = array_flip($this->flattenTypeList($tagArgument->getType()));
        }

        if (empty($argumentTypes)) {
            $argumentTypes["mixed"] = true;
        }

        switch (true) {
            case isset($argumentTypes["iterable"]):
            case isset($argumentTypes[\Iterator::class]):
            case isset($argumentTypes[\Traversable::class]):
            case isset($argumentTypes[\ArrayAccess::class]):
            case isset($argumentTypes[TagIterator::class]):
                return $value;
            case isset($argumentTypes["mixed"]):
            case isset($argumentTypes["array"]):
                $valueArray = [];
                foreach ($value as $key => $service) {
                    $valueArray[$key] = $service;
                }
                return $valueArray;
            default:
                throw new ReferenceException(
                    "Could not resolve tag collection for '$tag'. The type for $class::$method, argument " .
                    (is_int($argumentKey) ? "#$argumentKey" : "'$argumentKey'") .
                    " does not allow a value containing a collection of services"
                );
        }
    }

    /**
     * This method keeps the positions of replaced parameters in sync for every performed replacement
     *
     * @param int $start
     * @param int $nameLength
     * @param int $replacementLength
     */
    protected function adjustReplacedParams(int $start, int $nameLength, int $replacementLength): void
    {
        $lengthDiff = $replacementLength - ($nameLength + 2);
        // Adjust the end position of any replaced params by the difference in length, if start and end fall inside it
        foreach ($this->replacedParams as $name => $replacements) {
            foreach ($replacements as $i => [$checkStart, $checkEnd]) {
                if ($start >= $checkStart && $start <= $checkEnd) {
                    $checkEnd += $lengthDiff;
                    $replacements[$i] = [$checkStart, $checkEnd];
                }
            }
            $this->replacedParams[$name] = $replacements;
        }
    }

    /**
     * @param ?\ReflectionType $type
     * @return array
     */
    protected function flattenTypeList(?\ReflectionType $type): array
    {
        $types = [];
        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                $types = array_merge($types, $this->flattenTypeList($subType));
            }
        } elseif ($type instanceof \ReflectionNamedType) {
            $types[] = $type->getName();
        }
        // No intersection types allowed
        return $types;
    }

} 
