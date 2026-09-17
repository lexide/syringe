<?php

namespace Lexide\Syringe\Test\Unit\Container;

use Lexide\Syringe\Container\ServiceFactory;
use Lexide\Syringe\Container\ServiceFactoryFactory;
use Lexide\Syringe\Exception\ServiceException;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class ServiceFactoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected Container|MockInterface $container;
    protected ReferenceResolverInterface|MockInterface $resolver;
    protected object $serviceMock;

    protected ServiceFactory $factory;

    public function setUp(): void
    {
        $this->container = \Mockery::mock(Container::class);
        $this->resolver = \Mockery::mock(ReferenceResolverInterface::class);
        $this->factory = new ServiceFactory();
        $this->factory->setContainer($this->container);
        $this->factory->setReferenceResolver($this->resolver);

        $this->serviceMock = new class () {
            protected array $args = [];
            protected array $calls = [];

            public function __construct(...$args)
            {
                $this->args = $args;
            }

            public function getConstructorArgs(): array
            {
                return $this->args;
            }

            public function setFoo(...$args): void
            {
                $this->calls["setFoo"] = $args;
            }

            public function callBar(...$args): void
            {
                $this->calls["callBar"] = $args;
            }

            public function invokeBaz(...$args): void
            {
                $this->calls["invokeBaz"] = $args;
            }

            public function getCalls(): array
            {
                return $this->calls;
            }
        };
    }

    public function testContainerDependency()
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches("/container.*not.*set/");

        $factory = new ServiceFactory();
        $factory->setReferenceResolver($this->resolver);
        $factory->createService(ServiceFactory::class, [], [], []);
    }

    public function testResolverDependency()
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches("/reference resolver.*not.*set/");

        $factory = new ServiceFactory();
        $factory->setContainer($this->container);
        $factory->createService(ServiceFactory::class, [], [], []);
    }

    public function testCreatingStubs()
    {
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessageMatches("/stub service/");

        $this->factory->createStub("foo", []);
    }

    public function testCreatingServices()
    {
        $this->assertInstanceOf(ServiceFactory::class, $this->factory->createService(ServiceFactory::class, [], [], []));
    }

    public function testCreatingServicesWithFactoryServices()
    {
        $mockServiceInstance = \Mockery::mock(ServiceFactory::class);
        $serviceFactoryFactory = \Mockery::mock(ServiceFactoryFactory::class);
        $method = "create";
        $arguments = [
            $this->resolver
        ];

        $factoryService = "factoryService";
        $factory = [
            "service" => $factoryService,
            "method" => $method
        ];

        $this->resolver->shouldReceive("resolveParameter")->andReturnArg(0);
        $this->resolver->shouldReceive("resolveService")
            ->with($factoryService, \Mockery::any())
            ->once()
            ->andReturn($serviceFactoryFactory);
        $this->resolver->shouldReceive("resolveArgument")->andReturnArg(0);
        $serviceFactoryFactory->shouldReceive("create")->withArgs($arguments)->once()->andReturn($mockServiceInstance);

        $this->assertSame($mockServiceInstance, $this->factory->createService(ServiceFactory::class, $factory, $arguments, []));
    }

    public function testCreatingServicesWithFactoryClasses()
    {

        $factoryClass = new class () {
            protected static array $callArgs = [];

            public static function create(string $foo, int $bar, bool $baz): ServiceFactory
            {
                self::$callArgs[] = [$foo, $bar, $baz];
                return new ServiceFactory();
            }

            public static function getCallCount(): int
            {
                return count(self::$callArgs);
            }

            public static function getCallArgs(): array
            {
                return self::$callArgs;
            }
        };

        $method = "create";
        $arguments = [
            "foo",
            123,
            true
        ];

        $factory = [
            "class" => $factoryClass::class,
            "method" => $method
        ];

        $this->resolver->shouldReceive("resolveParameter")->andReturnArg(0);
        $this->resolver->shouldReceive("resolveArgument")->andReturnArg(0);

        $this->assertInstanceOf(ServiceFactory::class, $this->factory->createService(ServiceFactory::class, $factory, $arguments, []));
        $this->assertSame(1, $factoryClass::getCallCount(), "The call count for the static factory class was incorrect");
        $this->assertSame($arguments, $factoryClass::getCallArgs()[0], "The call arguments for the static factory class were incorrect");
    }

    public function testResolvingConstructorArguments()
    {
        $arguments = [
            "foo",
            "bar",
            "baz",
            "fiz" => [
                "foo",
                "baz"
            ]
        ];

        $expectedArguments = [
            "one",
            "two",
            "three",
            "four" => [
                "one",
                "three"
            ]
        ];

        $argumentMap = ["foo" => "one", "bar" => "two", "baz" => "three"];
        $parameterMap = ["fiz" => "four"];

        $this->resolver->shouldReceive("resolveParameter")->andReturnUsing(fn($key) => $parameterMap[$key] ?? $key);
        $this->resolver->shouldReceive("resolveArgument")->andReturnUsing(fn($key) => $argumentMap[$key] ?? $key);

        $service = $this->factory->createService($this->serviceMock::class, [], $arguments, []);

        $this->assertInstanceOf($this->serviceMock::class, $service);
        $this->assertSame($expectedArguments, $service->getConstructorArgs());

    }

    public function testResolvingCallArguments()
    {

        $calls = [
            [
                "method" => "setFoo",
                "arguments" => [
                    "foo",
                    "bar"
                ]
            ],
            [
                "method" => "callBar",
                "arguments" => [
                    [
                        "baz",
                        "fiz"
                    ],
                    "foo"
                ]
            ],
            [
                "method" => "invokeBaz",
                "arguments" => [
                    [
                        "bar" => "a",
                        "baz" => "b",
                        "fiz" => "c",
                    ]
                ]
            ]
        ];

        $resolveMap = ["foo" => "one", "bar" => "two", "baz" => "three", "fiz" => "four"];

        $expectedArgs = [
            "setFoo" => [
                "one",
                "two"
            ],
            "callBar" => [
                [
                    "three",
                    "four"
                ],
                "one"
            ],
            "invokeBaz" => [
                [
                    "two" => "a",
                    "three" => "b",
                    "four" => "c"
                ]
            ]
        ];

        $resolveFn = fn($key) => $resolveMap[$key] ?? $key;

        $this->resolver->shouldReceive("resolveParameter")->andReturnUsing($resolveFn);
        $this->resolver->shouldReceive("resolveArgument")->andReturnUsing($resolveFn);

        $service = $this->factory->createService($this->serviceMock::class, [], [], $calls);

        $this->assertInstanceOf($this->serviceMock::class, $service);
        $actualCalls = $service->getCalls();
        foreach ($expectedArgs as $method => $args) {
            $this->assertArrayHasKey($method, $actualCalls);
            $this->assertSame($args, $actualCalls[$method]);
        }

    }

}
