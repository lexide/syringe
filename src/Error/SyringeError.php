<?php

namespace Lexide\Syringe\Error;

class SyringeError
{

    protected string $type;
    protected string $message;
    protected array $context;

    /**
     * @param string $type
     * @param string $message
     * @param array $context
     */
    public function __construct(string $type, string $message, array $context = [])
    {
        $this->type = $type;
        $this->message = $message;
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function addContext(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

}