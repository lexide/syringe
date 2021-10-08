<?php

namespace Lexide\Syringe\Test\Integration\Imports;

use Lexide\Syringe\ContainerBuilder;
use Lexide\Syringe\Exception\LoaderException;
use Lexide\Syringe\Loader\YamlLoader;
use Lexide\Syringe\ReferenceResolver;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

abstract class AbstractImportTestConfigurer extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $configDirectory;
    /**
     * @var ContainerBuilder
     */
    protected $builder;

    /**
     * @throws LoaderException
     */
    public function setUp(): void
    {
        $this->configDirectory = vfsStream::setup();
        $resolver = new ReferenceResolver();
        $this->builder = new ContainerBuilder($resolver);
        $this->builder->addLoader(new YamlLoader());
        $this->builder->addConfigPath($this->configDirectory->url());
    }
}