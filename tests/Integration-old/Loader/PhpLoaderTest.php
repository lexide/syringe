<?php

namespace Lexide\Syringe\Test\Unit\Loader;

use Lexide\Syringe\ContainerBuilder;
use Lexide\Syringe\Loader\PhpLoader;
use Lexide\Syringe\ReferenceResolver;
use PHPUnit\Framework\TestCase;

class PhpLoaderTest extends TestCase
{
    public function testParameterReturnCorrect()
    {
        $referenceResolver = new ReferenceResolver();
        $containerBuilder = new ContainerBuilder($referenceResolver, [__DIR__]);
        $containerBuilder->addLoader(new PhpLoader());
        $containerBuilder->addConfigFile("PhpLoaderExampleFile.php");
        $container = $containerBuilder->createContainer();
        $this->assertEquals($container->offsetGet("Foo"), "Bar");
        $this->assertEquals(\DateTime::class, get_class($container->offsetGet("datetime")));
    }
}
