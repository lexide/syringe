<?php

namespace Lexide\Syringe\Loader;

use Lexide\Syringe\Exception\LoaderException;

class LoaderRegistry
{

    /**
     * @var array
     */
    protected $loaders;

    /**
     * @param LoaderInterface[] $loaders
     */
    public function __construct(array $loaders)
    {
        $this->setLoaders($loaders);
    }

    /**
     * @param LoaderInterface[] $loaders
     */
    public function setLoaders(array $loaders): void
    {
        $this->loaders = [];
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }

    /**
     * @param LoaderInterface $loader
     */
    public function addLoader(LoaderInterface $loader): void
    {
        $this->loaders[] = $loader;
    }

    /**
     * @param string $file
     * @return LoaderInterface
     * @throws LoaderException
     */
    public function findLoaderForFile(string $file): LoaderInterface
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($file)) {
                return $loader;
            }
        }
        throw new LoaderException(sprintf("The file '%s' is not supported by any of the available loaders", $file));
    }

}