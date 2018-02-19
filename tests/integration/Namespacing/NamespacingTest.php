<?php

namespace Lexide\Syringe\IntegrationTests\Namespacing;

use Lexide\Syringe\ContainerBuilder;
use Lexide\Syringe\Loader\JsonLoader;
use Lexide\Syringe\Loader\YamlLoader;
use Lexide\Syringe\ReferenceResolver;

class NamespacingTest extends \PHPUnit_Framework_TestCase
{
    public function testParameterLayering()
    {
        $resolver = new ReferenceResolver();
        $builder = new ContainerBuilder($resolver);
        $builder->addLoader(new YamlLoader());
        $builder->addLoader(new JsonLoader());
        $builder->addConfigPath(__DIR__);
        $builder->addConfigFiles([
            "dependency" => "dependency.yml",
            "parent" => "parent.yml"
        ]);
        $container = $builder->createContainer();
        $this->assertEquals("42", $container["parent.my_api_key"]);
        $this->assertEquals("42", $container["dependency.key_using_api_key"]);
        $this->assertEquals("42", $container["dependency.key_using_key_using_api_key"]);
    }
}
