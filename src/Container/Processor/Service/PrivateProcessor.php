<?php

namespace Lexide\Syringe\Container\Processor\Service;

use Lexide\Syringe\Reference\ReferenceResolverInterface;

class PrivateProcessor
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
     * @return string - the (possibly) obfuscated key
     */
    public function process(string $key, array $definition): string
    {

        if (!empty($definition["private"])) {
            // if a service is private, create a random key for it and save the reference against the actual service key
            $hashedName = md5($key . microtime(true));

            $this->resolver->registerPrivateService($hashedName, $key);
            $key = $hashedName;
        }

        return $key;
    }

}