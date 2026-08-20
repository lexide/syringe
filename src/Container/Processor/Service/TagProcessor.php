<?php

namespace Lexide\Syringe\Container\Processor\Service;

use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\ReferenceException;
use Lexide\Syringe\Reference\Reference;
use Lexide\Syringe\Reference\ReferenceResolverInterface;
use Lexide\Syringe\Tag\TagCollection;
use Pimple\Container;

class TagProcessor
{

    protected ReferenceResolverInterface $resolver;

    /**
     * @param ReferenceResolverInterface $resolver
     */
    public function __construct(ReferenceResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @param string $key
     * @param array $definition
     * @param Container $container
     * @throws ConfigException
     * @throws ReferenceException
     */
    public function process(string $key, array $definition, Container $container): void
    {
        if (!empty($definition["tags"])) {
            foreach ($definition["tags"] as $tagDefinition) {

                $tag = Reference::TAG_CHAR . $tagDefinition["tag"];

                // Create the context array for this tag
                // Add any custom context verbatim, then overwrite "value" and "order" from the definition
                $context = array_merge(
                    $tagDefinition["context"] ?? [],
                    array_intersect_key($tagDefinition, ["key" => true, "order" => true])
                );

                if (!isset($container[$tag])) {
                    $container[$tag] = fn() => new TagCollection();
                }

                $collection = $container[$tag];
                if (!$collection instanceof TagCollection) {
                    throw new ConfigException("Could not add service '$key' to the tag '$tag' as it is not a TagCollection");
                }
                $collection->addService($key, $this->resolver->resolveParameter($context, $container));
            }
        }
    }

}