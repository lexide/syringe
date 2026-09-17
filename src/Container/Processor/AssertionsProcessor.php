<?php

namespace Lexide\Syringe\Container\Processor;

use Lexide\Syringe\Assertion\AssertionInterface;
use Lexide\Syringe\Assertion\AssertionRegistry;
use Lexide\Syringe\Container\ContainerOptions;
use Lexide\Syringe\Error\ReportErrorsTrait;
use Lexide\Syringe\Exception\ConfigException;
use Pimple\Container;
use Psr\Log\LoggerInterface;

class AssertionsProcessor
{
    use ReportErrorsTrait;

    protected AssertionRegistry $assertionRegistry;

    /**
     * @param AssertionRegistry $assertionRegistry
     */
    public function __construct(AssertionRegistry $assertionRegistry)
    {
        $this->assertionRegistry = $assertionRegistry;
    }

    /**
     * @param array $serviceDefinitions
     * @param Container $container
     * @param ContainerOptions $options
     * @throws ConfigException
     */
    public function process(array $serviceDefinitions, Container $container, ContainerOptions $options): void
    {
        if (!$options->processAssertions()) {
            return;
        }

        $logger = $options->errorLogger();
        if ($logger instanceof LoggerInterface) {
            $this->errorLogger = $logger;
        }

        $errors = [];
        foreach ($serviceDefinitions["assertions"] ?? [] as $assertionDefinition) {
            $reference = $assertionDefinition["reference"];
            $checks = $assertionDefinition["checks"];
            $type = match (true) {
                isset($serviceDefinitions["parameters"][$reference]) => AssertionInterface::TYPE_PARAMETER,
                isset($serviceDefinitions["services"][$reference]) => AssertionInterface::TYPE_SERVICE,
                default => null
            };
            $definition = $serviceDefinitions["parameters"][$reference] ??
                $serviceDefinitions["services"][$reference] ??
                null;
            foreach ($checks as $check) {
                $assertion = $this->assertionRegistry->getAssertion($check["assert"]);
                $errors = array_merge(
                    $errors,
                    $assertion->assert($check["operand"] ?? null, $reference, $type, $definition, $container)
                );
            }

        }

        $this->reportErrors($errors, $options->ignoreAssertionWarnings());
    }

}