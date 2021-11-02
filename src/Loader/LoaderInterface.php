<?php

namespace Lexide\Syringe\Loader;

interface LoaderInterface {

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param $file
     * @return bool
     */
    public function supports($file): bool;

    /**
     * @param $file
     * @return array
     */
    public function loadFile($file): array;

} 
