<?php

namespace Lexide\Syringe\Reference;

class ReferenceHelper
{

    /**
     * @param string $value
     * @return bool
     */
    public function isServiceReference(string $value): bool
    {
        return strlen($value) > 1 && $value[0] === Reference::SERVICE_CHAR;
    }

    /**
     * @param string $serviceReference
     * @return string
     */
    public function getServiceKey(string $serviceReference): string
    {
        return ltrim($serviceReference, Reference::SERVICE_CHAR);
    }

    /**
     * @param string $serviceKey
     * @return string
     */
    public function getServiceReference(string $serviceKey): string
    {
        // Run getServiceKey to make sure that we don't add the service character when it already exists
        return Reference::SERVICE_CHAR . $this->getServiceKey($serviceKey);
    }

    /**
     * @param string $value
     * @return bool
     */
    public function isTagReference(string $value): bool
    {
        return strlen($value) > 1 && $value[0] === Reference::TAG_CHAR;
    }

    /**
     * @param string $string
     * @param int $offset
     * @return ?string
     */
    public function findNextParameter(string $string, int $offset = 0): ?string
    {
        return $this->findNextEmbeddedReference($string, Reference::PARAMETER_CHAR, $offset);
    }

    /**
     * @param string $string
     * @param int $offset
     * @return ?string
     */
    public function findNextConstant(string $string, int $offset = 0): ?string
    {
        return $this->findNextEmbeddedReference($string, Reference::CONSTANT_CHAR, $offset);
    }

    /**
     * @param string $string
     * @param string $parameter
     * @param string $replacement
     * @param bool $removeChars
     * @return ?string
     */
    public function replaceParameterReference(string $string, string $parameter, string $replacement, bool $removeChars = false): ?string
    {
        return $this->replaceEmbeddedReference($string, $parameter, Reference::PARAMETER_CHAR, $replacement, $removeChars);
    }

    /**
     * @param string $string
     * @param string $parameter
     * @param string $replacement
     * @param bool $removeChars
     * @return ?string
     */
    public function replaceConstantReference(string $string, string $parameter, string $replacement, bool $removeChars = false): ?string
    {
        return $this->replaceEmbeddedReference($string, $parameter, Reference::CONSTANT_CHAR, $replacement, $removeChars);
    }

    /**
     * @param string $string
     * @return string
     */
    public function unescapeCharacters(string $string): string
    {
        // constant character must be listed last or the regex character class will be negated
        $chars = Reference::PARAMETER_CHAR . Reference::CONSTANT_CHAR;
        return preg_replace("/\\\\([$chars])/", "\$1", $string);
    }

    /**
     * @param string $string
     * @param string $char
     * @param int $offset
     * @return ?string
     */
    protected function findNextEmbeddedReference(string $string, string $char, int $offset): ?string
    {
        if ($offset > 0) {
            $string = substr($string, $offset);
        }

        if (preg_match($this->getEmbeddedReferenceRegex(preg_quote($char)), $string, $matches)) {
            return $matches[4];
        }
        return null;
    }

    /**
     * @param string $string
     * @param string $reference
     * @param string $char
     * @param string $replacement
     * @param bool $removeChars
     * @return ?string
     */
    protected function replaceEmbeddedReference(string $string, string $reference, string $char, string $replacement, bool $removeChars): ?string
    {
        $pattern = "/" . preg_quote($char . $reference . $char) . "/u";
        if (!$removeChars) {
            $replacement = $char . $replacement . $char;
        }

        return preg_replace($pattern, $replacement, $string, 1);
    }

    /**
     * @param string $char
     * @return string
     */
    protected function getEmbeddedReferenceRegex(string $char): string
    {
        return "/(^|[^\\\\])((\\\\\\\\)*){$char}([^$char]+)$char/u";
    }

}