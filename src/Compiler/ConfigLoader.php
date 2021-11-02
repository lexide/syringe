<?php

namespace Lexide\Syringe\Compiler;

use Lexide\Syringe\Exception\ConfigException;
use Lexide\Syringe\Exception\LoaderException;
use Lexide\Syringe\Loader\LoaderRegistry;

class ConfigLoader
{

    /**
     * @var LoaderRegistry
     */
    protected $loaderRegistry;

    /**
     * @var string[]
     */
    protected $configPaths;

    /**
     * @param LoaderRegistry $loaderRegistry
     */
    public function __construct(LoaderRegistry $loaderRegistry)
    {
        $this->loaderRegistry = $loaderRegistry;
    }

    /**
     * @param string[] $configPaths
     */
    public function setConfigPaths(array $configPaths)
    {
        $this->configPaths = [];
        foreach ($configPaths as $path) {
            $this->configPaths[] = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * @param string $file
     * @param string $relativeTo
     * @return array
     * @throws ConfigException
     * @throws LoaderException
     */
    public function loadConfig(string $file, string $relativeTo = ""): array
    {
        if (!empty($relativeTo)) {
            $filePath = $this->findRelativePath($file, $relativeTo);
        }
        if (empty($filePath)) {
            $filePath = $this->findFilePath($file);
        }

        if (empty($filePath)) {
            $relativeMessage = empty($relativeTo)? "": " or relative to '$relativeTo')";
            throw new ConfigException("The config file '$file' could not be found in any of the configured paths$relativeMessage");
        }

        $loader = $this->loaderRegistry->findLoaderForFile($filePath);
        return [$loader->loadFile($filePath), $filePath];
    }

    /**
     * @param string $file
     * @param string $relativeTo
     * @return string
     */
    protected function findRelativePath(string $file, string $relativeTo): string
    {
        if (!is_dir($relativeTo)) {
            $relativeTo = dirname($relativeTo);
        }
        $ds = DIRECTORY_SEPARATOR;

        // remove dot directories from the file path
        $file = preg_replace("%(^|\\$ds)(\\.{1,2}(\\$ds|\$))+%u", "\$1", $file);

        // normalise path
        $file = ltrim($file, $ds);
        $relativeTo = rtrim($relativeTo, $ds);
        $filePath = $relativeTo . $ds . $file;

        return file_exists($filePath)? $filePath: "";
    }

    /**
     * @param string $file
     * @return string
     */
    protected function findFilePath(string $file): string
    {
        foreach ($this->configPaths as $path) {
            $filePath = $path . $file;
            if (file_exists($filePath)) {
                return $filePath;
            }
        }
        return "";
    }

}