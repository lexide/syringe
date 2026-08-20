<?php

namespace Lexide\Syringe\Test\Unit\Container\Processor\Service;

use Lexide\Syringe\Container\Processor\Service\PrivateProcessor;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class PrivateProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    use MockeryPHPUnitIntegration;

    protected ReferenceResolverInterface|MockInterface $resolver;

    public function setUp(): void
    {
        $this->resolver = \Mockery::mock(ReferenceResolverInterface::class);
    }

    public function testPrivateService()
    {
        $key = "foo";
        $expectedKey = "";

        $processor = new PrivateProcessor($this->resolver);
        $this->resolver->shouldReceive("registerPrivateService")->once()->andReturnUsing(function($privateKey) use (&$expectedKey) {
            $expectedKey = $privateKey;
        });

        $actualKey = $processor->process($key, ["private" => true]);
        $this->assertSame($expectedKey, $actualKey);
        $this->assertNotSame($key, $actualKey);
    }

    public function testPublicService()
    {
        $key = "foo";

        $processor = new PrivateProcessor($this->resolver);

        $this->assertSame($key, $processor->process($key, []));
    }

}
