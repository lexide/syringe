<?php

use Lexide\Syringe\Syringe;

class PrivacyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Pimple\Container
     */
    protected $container;

    public function setUp()
    {
        $configFiles = [
            "service.json",
            "private_test" => "aliased.json"
        ];

        Syringe::init(__DIR__, $configFiles);
        $this->container = Syringe::createContainer();
    }

    public function testAliasingAndPrivacy()
    {
        $collection = $this->container["tagCollection"];
        $duds = $this->container["duds"];

        $this->assertSame($collection, $duds, "Aliased service did not return the same object as the original service");

        // check aliases are namespaced
        $this->assertFalse($this->container->offsetExists("publicAlias"), "Aliases should be namespaced where appropriate.");
        $this->assertInstanceOf(
            "\\Lexide\\Syringe\\IntegrationTests\\Service\\DudConsumer",
            $this->container["private_test.publicAlias"],
            "Namespaced Alias was not accessible"
        );

        // check private services are hidden
        $this->assertFalse(
            $this->container->offsetExists("private_test.privateService"),
            "Services marked as private should not be accessible from the container directly"
        );

        try {
            $service = $this->container["privacyIgnorer"];
            $this->fail("Services marked as private should not be accessible from outside of their alias");
        } catch (\Lexide\Syringe\Exception\ReferenceException $e) {
            // expected behaviour
        }

        // check private services can be used within the same namespace
        try {
            $service = $this->container["private_test.usesPrivateService"];
        } catch (\Lexide\Syringe\Exception\ReferenceException $e) {
            $this->fail("An unexpected ReferenceException was thrown when trying to access a service that uses a private service:\n" . $e->getMessage());
        }

        // This is a bug. Need to work out how to make aliases respect privacy
        //if ($container->offsetExists("private_test.privateAlias")) {
        //    throw new \Exception("Aliases do not respect privacy");
        //}
    }
}
