<?php

namespace Lexide\Syringe\Schema;

class SchemaLintError
{

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array
     */
    protected $replacements;

    /**
     * @param string $message
     * @param array $replacements
     */
    public function __construct(string $message, array $replacements = [])
    {
        $this->message = $message;
        $this->replacements = $replacements;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array
     */
    public function getReplacements(): array
    {
        return $this->replacements;
    }

}