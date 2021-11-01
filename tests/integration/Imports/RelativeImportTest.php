<?php

namespace Lexide\Syringe\IntegrationTests\Imports;

use org\bovigo\vfs\vfsStream;
use Symfony\Component\Yaml\Yaml;

class RelativeImportTest extends AbstractImportTestConfigurer
{
    /**
     * @throws \Lexide\Syringe\Exception\ConfigException
     * @throws \Lexide\Syringe\Exception\LoaderException
     * @throws \Lexide\Syringe\Exception\ReferenceException
     */
    public function testParameterImports()
    {
        $keyToCheck = "thisKeyShouldBePresent";

        $relativeImportConfigFile = 'relativeImport.yml';
        $relativeImportConfigContents = Yaml::dump(
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
                    $relativeImportConfigFile
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
                $importConfigFolder => [
                    $importConfigFile => $importConfigContents,
                    $relativeImportConfigFile => $relativeImportConfigContents
                ]
            ],
            $this->configDirectory
        );

        $this->builder->addConfigFiles([$baseConfigFile]);

        $container = $this->builder->createContainer();

        $this->assertArrayHasKey($keyToCheck, $container);
    }
}
