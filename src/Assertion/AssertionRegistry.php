<?php

namespace Lexide\Syringe\Assertion;

use Lexide\Syringe\Exception\ConfigException;

class AssertionRegistry
{

    protected array $assertions;

    /**
     * @param array $assertions
     */
    public function __construct(array $assertions)
    {
        $this->setAssertions($assertions);
    }

    /**
     * @param array $assertions
     */
    protected function setAssertions(array $assertions): void
    {
        $this->assertions = [];
        foreach ($assertions as $name => $assertion) {
            $this->addAssertion($name, $assertion);
        }
    }

    /**
     * @param string $name
     * @param AssertionInterface $assertion
     */
    protected function addAssertion(string $name, AssertionInterface $assertion): void
    {
        $this->assertions[$name] = $assertion;
    }

    /**
     * @param string $name
     * @return AssertionInterface
     * @throws ConfigException
     */
    public function getAssertion(string $name): AssertionInterface
    {
        if (empty($this->assertions[$name])) {
            throw new ConfigException("No assertion is registered wit the name '$name'");
        }
        return $this->assertions[$name];
    }

}