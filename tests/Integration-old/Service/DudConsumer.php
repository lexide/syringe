<?php

namespace Lexide\Syringe\Test\Integration\Service;

/**
 * DudConsumer
 */
class DudConsumer
{

    protected $dud;

    public function __construct(DudService $dud)
    {
        $this->dud = $dud;
    }

}
