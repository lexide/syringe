<?php

namespace Lexide\Syringe\Test\Integration\Imports;

use org\bovigo\vfs\vfsStream;
use Symfony\Component\Yaml\Yaml;

class ImportTest extends AbstractImportTestConfigurer
{
    /**
     * @throws \Lexide\Syringe\Exception\ConfigException
     * @throws \Lexide\Syringe\Exception\LoaderException
     * @throws \Lexide\Syringe\Exception\ReferenceException
     */
    public function testParameterImports()
    {
        $keyToCheck = "thisKeyShouldBePresent";
        $baseConfigFile = 'base.yml';
        $importConfigFile = "imported.yml";
        $baseConfigContents = Yaml::dump(['imports' => [$importConfigFile]]);
        $importConfigContents = Yaml::dump(['parameters'=> [$keyToCheck => true]]);

        vfsStream::create(
            [
                $baseConfigFile => $baseConfigContents,
                $importConfigFile => $importConfigContents
            ],
            $this->configDirectory
        );

        $this->builder->addConfigFiles([$baseConfigFile]);

        $container = $this->builder->createContainer();

        $this->assertArrayHasKey($keyToCheck, $container);
    }
}
