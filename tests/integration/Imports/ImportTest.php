<?php

namespace Lexide\Syringe\IntegrationTests\Imports;

use Lexide\Syringe\ContainerBuilder;
use Lexide\Syringe\Loader\JsonLoader;
use Lexide\Syringe\Loader\YamlLoader;
use Lexide\Syringe\ReferenceResolver;

class NamespacingTest extends \PHPUnit_Framework_TestCase
{
    public function testParameterImports()
    {
        $resolver = new ReferenceResolver();
        $builder = new ContainerBuilder($resolver);
        $builder->addLoader(new YamlLoader());
        $builder->addConfigPath(__DIR__);
        $builder->addConfigFiles([
            "base.yml"
        ]);
        $container = $builder->createContainer();
        $this->assertEquals("bar", $container["foo"]);
    }
}
