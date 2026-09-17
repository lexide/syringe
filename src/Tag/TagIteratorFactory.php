<?php

namespace Lexide\Syringe\Tag;

use Pimple\Container;

class TagIteratorFactory
{

    /**
     * @param Container $container
     * @param ?TagCollection $collection
     * @return TagIterator
     */
    public function create(Container $container, ?TagCollection $collection): TagIterator
    {
        if (is_null($collection)){
            $collection = new TagCollection();
        }

        return new TagIterator($container, $collection);
    }

}