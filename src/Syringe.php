<?php

namespace Lexide\Syringe;

use Lexide\Syringe\Assertion\AssertionRegistry;
use Lexide\Syringe\Assertion\EnumAssertion;
use Lexide\Syringe\Assertion\FormatAssertion;
use Lexide\Syringe\Assertion\NotEmptyAssertion;
use Lexide\Syringe\Assertion\SyntaxAssertion;
use Lexide\Syringe\Assertion\TypeAssertion;
use Lexide\Syringe\Compiler\ConfigCompiler;
use Lexide\Syringe\Compiler\ConfigLoader;
use Lexide\Syringe\Compiler\DefinitionLoader;
use Lexide\Syringe\Container\ContainerBuilder;
use Lexide\Syringe\Container\ContainerFactory;
use Lexide\Syringe\Container\ContainerOptions;
use Lexide\Syringe\Container\Processor\AssertionsProcessor;
use Lexide\Syringe\Container\Processor\ParametersProcessor;
use Lexide\Syringe\Container\Processor\Service\AliasOfProcessor;
use Lexide\Syringe\Container\Processor\Service\ClassProcessor;
use Lexide\Syringe\Container\Processor\Service\FactoryProcessor;
use Lexide\Syringe\Container\Processor\Service\PrivateProcessor;
use Lexide\Syringe\Container\Processor\Service\StubProcessor;
use Lexide\Syringe\Container\Processor\Service\TagProcessor;
use Lexide\Syringe\Container\Processor\ServicesProcessor;
use Lexide\Syringe\Container\ServiceFactoryFactory;
use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\LoaderException;
use Lexide\Syringe\Loader\JsonLoader;
use Lexide\Syringe\Loader\LoaderRegistry;
use Lexide\Syringe\Loader\PhpLoader;
use Lexide\Syringe\Loader\YamlLoader;
use Lexide\Syringe\Normalisation\ApplyExtensionsNormaliser;
use Lexide\Syringe\Normalisation\CallArgumentsNormaliser;
use Lexide\Syringe\Normalisation\DefinitionsNormaliser;
use Lexide\Syringe\Normalisation\ExtensionCallsNormaliser;
use Lexide\Syringe\Normalisation\InheritanceNormaliser;
use Lexide\Syringe\Normalisation\NamespaceNormaliser;
use Lexide\Syringe\Normalisation\TagNormaliser;
use Lexide\Syringe\Provider\DefinitionProviderInterface;
use Lexide\Syringe\Provider\EnvironmentVariableProvider;
use Lexide\Syringe\Provider\ParameterMapProvider;
use Lexide\Syringe\Provider\SyringeProvider;
use Lexide\Syringe\Reference\ReferenceHelper;
use Lexide\Syringe\Reference\ReferenceResolver;
use Lexide\Syringe\Schema\SchemaLinter;
use Lexide\Syringe\Tag\TagIteratorFactory;
use Lexide\Syringe\Validation\ReferenceValidator;
use Lexide\Syringe\Validation\ReferenceValidatorHelper;
use Lexide\Syringe\Validation\SyntaxValidator;
use Lexide\Syringe\Validation\SyntaxValidatorFactory;
use Lexide\Syringe\Validation\TypeValidator;
use Pimple\Container;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class Syringe
{

    const CONTAINER_DEFINITION_CACHE_KEY = "syringe-container-definition";

    protected ContainerOptions $options;

    protected $configPaths = [];

    protected $configFiles = [];

    protected $providers = [];

    protected ?ReferenceHelper $referenceHelper = null;

    protected ?ErrorHelper $errorHelper = null;

    protected ?TypeValidator $typeValidator = null;

    /**
     * @param ContainerOptions $options
     */
    public function __construct(ContainerOptions $options)
    {
        $this->options = $options;
    }

    /**
     * @param array $paths
     */
    public function addConfigPaths(array $paths): void
    {
        foreach ($paths as $path) {
            $this->addConfigPath($path);
        }
    }

    /**
     * @param string $path
     */
    public function addConfigPath(string $path): void
    {
        if (!empty($path) && $path[0] != "/" && $appDir = $this->options->applicationDirectory()) {
            $path = rtrim($appDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
        }

        $this->configPaths[] = $path;
    }

    /**
     * @param string $file
     * @param string $namespace
     */
    public function addConfigFile(string $file, string $namespace = ""): void
    {
        $this->configFiles[] = ["file" => $file, "namespace" => $namespace];
    }

    /**
     * @param array $files
     */
    public function addConfigFiles(array $files): void
    {
        foreach ($files as $namespace => $file) {
            $this->addConfigFile($file, $namespace);
        }
    }

    /**
     * @param DefinitionProviderInterface $provider
     */
    public function addProvider(DefinitionProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @param array $providers
     */
    public function addProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    /**
     * @return Container|ContainerInterface
     * @throws ConfigException
     * @throws Exception\ReferenceException
     */
    public function build(): Container|ContainerInterface
    {
        if ($this->options->cacheCompiledDefinition()) {
            $compiledDefinitions = apcu_fetch(self::CONTAINER_DEFINITION_CACHE_KEY);
        }

        if (empty($compiledDefinitions)) {

            $errorLogger = $this->options->errorLogger();
            $ignoreWarnings = $this->options->ignoreCompilationWarnings();

            $this->processOptions();

            $loader = $this->getDefinitionLoader($errorLogger);

            $definitions = $loader->loadDefinitions(
                $this->configFiles,
                $this->providers,
                $this->options->skipSyntaxValidation(),
                $ignoreWarnings
            );

            $compiler = $this->getCompiler($errorLogger);
            $compiledDefinitions = $compiler->compile($definitions, $ignoreWarnings);

            if ($this->options->cacheCompiledDefinition()) {
                apcu_store(self::CONTAINER_DEFINITION_CACHE_KEY, $compiledDefinitions);
            }
        }

        $containerBuilder = $this->getContainerBuilder();
        return $containerBuilder->createContainer($compiledDefinitions["definitions"], $this->options);
    }

    /**
     *
     */
    protected function processOptions(): void
    {
        $this->providers = [];

        // app directory
        $appDirKey = $this->options->applicationDirectoryKey();
        $appDir = $this->options->applicationDirectory();
        if (!empty($appDirKey) && !empty($appDir)) {
            $this->addProvider(new ParameterMapProvider([$appDirKey => $appDir], "ApplicationDirectoryProvider"));
        }

        $environmentVariableMap = $this->options->environmentVariableMap();
        if (!empty($environmentVariableMap)) {
            $this->addProvider(new EnvironmentVariableProvider($environmentVariableMap));
        }

        if ($this->options->useIncludePath()) {
            $this->addConfigPaths(explode(":", get_include_path()));
        }

        // add the standard syringe provider
        $this->addProvider(new SyringeProvider());

    }

    /**
     * @return ReferenceHelper
     */
    protected function getReferenceHelper(): ReferenceHelper
    {
        if (!$this->referenceHelper instanceof ReferenceHelper) {
            $this->referenceHelper = new ReferenceHelper();
        }
        return $this->referenceHelper;
    }

    /**
     * @return ErrorHelper
     */
    protected function getErrorHelper(): ErrorHelper
    {
        if (!$this->errorHelper instanceof ErrorHelper) {
            $this->errorHelper = new ErrorHelper();
        }
        return $this->errorHelper;
    }

    /**
     * @return TypeValidator
     */
    protected function getTypeValidator(): TypeValidator
    {
        if (!$this->typeValidator instanceof TypeValidator) {
            $this->typeValidator = new TypeValidator();
        }
        return $this->typeValidator;
    }

    /**
     * @param ?LoggerInterface $errorLogger
     * @return DefinitionLoader
     * @throws ConfigException
     */
    protected function getDefinitionLoader(?LoggerInterface $errorLogger): DefinitionLoader
    {
        $yamlLoader = new YamlLoader();

        $loaderRegistry = new LoaderRegistry([
            $yamlLoader,
            new JsonLoader(),
            new PhpLoader()
        ]);
        $loader = new ConfigLoader($loaderRegistry);
        $loader->setConfigPaths($this->configPaths);

        $schemaFilePath = dirname(__DIR__) . "/config/schemata.yml";
        try {
            $schemata = $yamlLoader->loadFile($schemaFilePath);
        } catch (LoaderException $e) {
            throw new ConfigException("Could not load schemata file", previous: $e);
        }

        return new DefinitionLoader(
            $loader,
            new SyntaxValidator($this->getReferenceHelper(), $this->getErrorHelper(), $this->getTypeValidator(), $schemata["schemata"] ?? []),
            $errorLogger
        );
    }

    /**
     * @param ?LoggerInterface $errorLogger
     * @return ConfigCompiler
     */
    protected function getCompiler(?LoggerInterface $errorLogger): ConfigCompiler
    {
        $referenceHelper = $this->getReferenceHelper();
        $errorHelper = $this->getErrorHelper();

        return new ConfigCompiler(
            new DefinitionsNormaliser(
                new ExtensionCallsNormaliser(),
                new NamespaceNormaliser($referenceHelper, $errorHelper),
                new InheritanceNormaliser($referenceHelper, $errorHelper),
                new ApplyExtensionsNormaliser($errorHelper),
                new TagNormaliser(),
                new CallArgumentsNormaliser()
            ),
            new ReferenceValidator(new ReferenceValidatorHelper($referenceHelper, $errorHelper), $referenceHelper, $errorHelper),
            $errorLogger
        );
    }

    /**
     * @return ContainerBuilder
     * @throws ConfigException
     */
    protected function getContainerBuilder(): ContainerBuilder
    {
        $resolver = new ReferenceResolver(
            $this->getReferenceHelper(),
            new TagIteratorFactory()
        );

        $serviceFactoryFactory = new ServiceFactoryFactory();
        $serviceFactory = $serviceFactoryFactory->create($resolver, $this->options->serviceFactoryClass());

        return new ContainerBuilder(
            new ContainerFactory($this->options->containerClass()),
            new ParametersProcessor($resolver),
            new ServicesProcessor(
                $serviceFactory,
                new StubProcessor($serviceFactory),
                new AliasOfProcessor($resolver),
                new ClassProcessor(),
                new PrivateProcessor($resolver),
                new FactoryProcessor(),
                new TagProcessor($resolver)
            ),
            new AssertionsProcessor($this->getAssertionRegistry())
        );
    }

    /**
     * @return AssertionRegistry
     */
    protected function getAssertionRegistry(): AssertionRegistry
    {
        $errorHelper = $this->getErrorHelper();

        return new AssertionRegistry(
            [
                "notEmpty" => new NotEmptyAssertion($errorHelper),
                "type" => new TypeAssertion($this->getTypeValidator(), $errorHelper),
                "format" => new FormatAssertion($errorHelper),
                "enum" => new EnumAssertion($errorHelper),
                "syntax" => new SyntaxAssertion(
                    new SchemaLinter(),
                    new SyntaxValidatorFactory($this->getReferenceHelper(), $this->getErrorHelper(), $this->getTypeValidator()),
                    $errorHelper
                )
            ]
        );
    }

}
