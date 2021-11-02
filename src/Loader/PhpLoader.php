<?php

namespace Lexide\Syringe\Loader;

use Lexide\Syringe\Exception\LoaderException;

/**
 * Load a config array from a PHP script
 */
class PhpLoader implements LoaderInterface {

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return "Php Loader";
    }

    /**
     * {@inheritDoc}
     */
    public function supports($file): bool
    {
        return pathinfo($file, PATHINFO_EXTENSION) == "php";
    }

    /**
     * {@inheritDoc}
     * @throws LoaderException
     */
    public function loadFile($file): array
    {
        if (!file_exists($file)) {
            throw new LoaderException("Requested file '{$file}' doesn't exist");
        }

        $data = include($file);

        if (!is_array($data)) {
            throw new LoaderException("Requested file '{$file}' is expected to return an array");
        }

        return $data;
    }

} 
