<?php

namespace Lexide\Syringe\Test\Integration;

class MockService
{

    protected int $thing;

    protected array $services;

    /**
     * @param int $thing
     */
    public function __construct(int $thing)
    {
        $this->thing = $thing;
    }

    /**
     * @param int $thing
     * @return MockService
     */
    public function create(int $thing): MockService
    {
        return self::staticCreate($thing);
    }

    /**
     * @param int $thing
     * @return MockService
     */
    public static function staticCreate(int $thing): MockService
    {
        return new MockService($thing);
    }

    /**
     * @param array $services
     */
    public function setServices(array $services): void
    {
        foreach ($services as $key => $service) {
            $this->addService($key, $service);
        }
    }

    /**
     * @param string|int $key
     * @param MockService $service
     */
    public function addService(string|int $key, MockService $service): void
    {
        $this->services[$key] = $service;
    }

    /**
     * @return int
     */
    public function getThing(): int
    {
        return $this->thing;
    }

    /**
     * @return array
     */
    public function getServices(): array
    {
        return $this->services;
    }

}