<?php

namespace Lexide\Syringe\Tag;

use Lexide\Syringe\Exception\ServiceException;
use Pimple\Container;

class TagIterator implements \Iterator, \ArrayAccess
{

    protected Container $container;
    protected TagCollection $tag;
    protected array $serviceNames;
    protected array $serviceContext;

    /**
     * @param Container $container
     * @param TagCollection $tag
     */
    public function __construct(Container $container, TagCollection $tag)
    {
        $this->container = $container;
        $this->tag = $tag;
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        $this->init();
        return $this->container[current($this->serviceNames)];
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $this->init();
        next($this->serviceNames);
    }

    /**
     * {@inheritDoc}
     */
    public function key(): mixed
    {
        $this->init();
        $key = key($this->serviceNames);
        $context = $this->serviceContext[$key];
        return $context["key"] ?? $key;

    }

    /**
     * @param ?string $key
     * @return array
     * @throws ServiceException
     */
    public function context(?string $key = null): array
    {
        $this->init();
        $index = is_null($key) ? key($this->serviceNames) : $this->getIndexFromKey($key);
        $this->checkIndex($index, $key);
        return $this->serviceContext[$index];
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        $this->init();
        return key($this->serviceNames) !== null;
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $this->init();
        reset($this->serviceNames);
    }

    protected function init(): void
    {
        if (empty($this->serviceNames)) {
            $services = $this->tag->getServices();
            uasort($services, [$this, "sortServices"]);
            $this->serviceNames = array_keys($services);
            $this->serviceContext = array_values($services);
        }
    }

    /**
     * @param array $contextA
     * @param array $contextB
     * @return int
     */
    protected function sortServices(array $contextA, array $contextB): int
    {
        // use max int so unordered services are after ordered ones
        return ($contextA["order"] ?? PHP_INT_MAX) <=> ($contextB["order"] ?? PHP_INT_MAX);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        $this->init();
        return !is_null($this->getIndexFromKey($offset));
    }

    /**
     * {@inheritDoc}
     * @throws ServiceException
     */
    public function offsetGet(mixed $offset): mixed
    {
        $this->init();
        $index = $this->getIndexFromKey($offset);
        $this->checkIndex($index, $offset);
        return $this->container[$this->serviceNames[$index]];
    }

    /**
     * {@inheritDoc}
     * @throws ServiceException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new ServiceException("Cannot set element. Tag iterators are immutable");
    }

    /**
     * {@inheritDoc}
     * @throws ServiceException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new ServiceException("Cannot unset element. Tag iterators are immutable");
    }

    /**
     * @param string|int $key
     * @return ?int
     */
    protected function getIndexFromKey(mixed $key): ?int
    {
        if (is_int($key) && isset($this->serviceNames[$key])) {
            return $key;
        }

        if (is_string($key)) {
            // search the contexts for this key
            foreach ($this->serviceContext as $i => $context) {
                if (($context["key"] ?? false) === $key) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @param ?int $index
     * @param string|int|null $key
     * @throws ServiceException
     */
    protected function checkIndex(?int $index, string|int|null $key): void
    {
        if (is_null($index)) {
            if (is_null($key)) {
                throw new ServiceException("There are no more services in this tag iterator");
            }
            throw new ServiceException("Could not find a service with the key '$key' in this tag iterator");
        }
    }
}