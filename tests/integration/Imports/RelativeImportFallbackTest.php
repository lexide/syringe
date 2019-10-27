<?php

namespace Lexide\Syringe\IntegrationTests\Imports;

use org\bovigo\vfs\vfsStream;
use Symfony\Component\Yaml\Yaml;

class RelativeImportFallbackTest extends AbstractImportTestConfigurer
{
    /**
     * Following relative import change this test ensures we maintain BC to previous functionality
     * @throws \Lexide\Syringe\Exception\ConfigException
     * @throws \Lexide\Syringe\Exception\LoaderException
     * @throws \Lexide\Syringe\Exception\ReferenceException
     */
    public function testParameterImports()
    {
        $keyToCheck = "thisKeyShouldBePresent";

        $importInBaseConfigFile = 'importInBase.yml';
        $importInBaseConfigContents = Yaml::dump(
            [
                'parameters' => [
                    $keyToCheck => true
                ]
            ]
        );

        $importConfigFile = 'import.yml';
        $importConfigFolder = 'directory';
        $importConfigContents = Yaml::dump(
            [
                'imports' => [
                    $importInBaseConfigFile
                ]
            ]
        );

        $baseConfigFile = 'base.yml';
        $baseConfigContents = Yaml::dump(
            [
                'imports' => [
                    $importConfigFolder . DIRECTORY_SEPARATOR . $importConfigFile
                ]
            ]
        );

        vfsStream::create(
            [
                $baseConfigFile => $baseConfigContents,
                $importInBaseConfigFile => $importInBaseConfigContents,
                $importConfigFolder => [
                    $importConfigFile => $importConfigContents
                ]
            ],
            $this->configDirectory
        );

        $this->builder->addConfigFiles([$baseConfigFile]);

        $container = $this->builder->createContainer();

        $this->assertArrayHasKey($keyToCheck, $container);
    }
}
