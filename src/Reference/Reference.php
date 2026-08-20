<?php

namespace Lexide\Syringe\Reference;

class Reference
{
    /**
     * The character that identifies a service
     * e.g. "@service"
     */
    const SERVICE_CHAR = "@";

    /**
     * The character that identifies the boundaries of a parameter
     * e.g. "%parameter%"
     */
    const PARAMETER_CHAR = "%";

    /**
     * The character that identifies a reference to a constant
     * e.g. "^MyCompany\\MyClass::MY_CONSTANT^" or "^STDOUT^"
     */
    const CONSTANT_CHAR = "^";

    /**
     * The character that identifies a collection of service with a specific tag
     */
    const TAG_CHAR = "#";

    /**
     * The character that identifies the separation between namespace and key
     */
    const NAMESPACE_SEPARATOR = ".";
}