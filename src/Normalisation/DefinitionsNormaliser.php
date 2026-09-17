<?php

namespace Lexide\Syringe\Normalisation;

use Lexide\Syringe\Exception\ReferenceException;

class DefinitionsNormaliser
{

    protected ExtensionCallsNormaliser $extensionCallsNormaliser;
    protected NamespaceNormaliser$namespaceNormaliser;
    protected InheritanceNormaliser$inheritanceNormaliser;
    protected ApplyExtensionsNormaliser $applyExtensionsNormaliser;
    protected TagNormaliser $tagNormaliser;
    protected CallArgumentsNormaliser $callArgumentsNormaliser;

    /**
     * @param ExtensionCallsNormaliser $extensionCallsNormaliser
     * @param NamespaceNormaliser $namespaceNormaliser
     * @param InheritanceNormaliser $inheritanceNormaliser
     * @param ApplyExtensionsNormaliser $applyExtensionsNormaliser
     * @param TagNormaliser $tagNormaliser
     * @param CallArgumentsNormaliser $callArgumentsNormaliser
     */
    public function __construct(
        ExtensionCallsNormaliser $extensionCallsNormaliser,
        NamespaceNormaliser $namespaceNormaliser,
        InheritanceNormaliser $inheritanceNormaliser,
        ApplyExtensionsNormaliser $applyExtensionsNormaliser,
        TagNormaliser $tagNormaliser,
        CallArgumentsNormaliser $callArgumentsNormaliser
    ) {
        $this->extensionCallsNormaliser = $extensionCallsNormaliser;
        $this->namespaceNormaliser = $namespaceNormaliser;
        $this->inheritanceNormaliser = $inheritanceNormaliser;
        $this->applyExtensionsNormaliser = $applyExtensionsNormaliser;
        $this->tagNormaliser = $tagNormaliser;
        $this->callArgumentsNormaliser = $callArgumentsNormaliser;
    }

    /**
     * @param array $definitions
     * @return array
     */
    public function normalise(array $definitions): array
    {
        // definitions separated by namespace keys
        $definitions = $this->extensionCallsNormaliser->normalise($definitions);

        [$definitions, $errors] = $this->namespaceNormaliser->normalise($definitions);
        if (!empty($errors)) {
            return [[], $errors];
        }

        // definition namespaces have been merged
        [$definitions, $errors] = $this->inheritanceNormaliser->normalise($definitions);
        if (!empty($errors)) {
            return [[], $errors];
        }

        [$definitions, $errors] = $this->applyExtensionsNormaliser->normalise($definitions);

        $definitions = $this->callArgumentsNormaliser->normalise(
            $this->tagNormaliser->normalise($definitions)
        );

        return [$definitions, $errors];
    }

}