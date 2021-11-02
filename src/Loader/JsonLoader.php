<?php

namespace Lexide\Syringe\Loader;

use Lexide\Syringe\Exception\LoaderException;

/**
 * Load a config file in JSON format
 */
class JsonLoader implements LoaderInterface {

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return "JSON Loader";
    }

    /**
     * {@inheritDoc}
     */
    public function supports($file): bool
    {
        return (pathinfo($file, PATHINFO_EXTENSION) == "json");
    }

    /**
     * {@inheritDoc}
     * @throws LoaderException
     */
    public function loadFile($file): array
    {
        try {
            $data = json_decode(file_get_contents($file), true, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new LoaderException(sprintf("Could not load the JSON file '%s'", $file), 0, $e);
        }

        return $data;
    }

} 
