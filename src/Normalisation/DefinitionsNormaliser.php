<?php

namespace Lexide\Syringe\Normalisation;

use Lexide\Syringe\Exception\ReferenceException;

class DefinitionsNormaliser
{

    /**
     * @var ExtensionCallsNormaliser
     */
    protected $extensionCallsNormaliser;

    /**
     * @var NamespaceNormaliser
     */
    protected $namespaceNormaliser;

    /**
     * @var InheritanceNormaliser
     */
    protected $inheritanceNormaliser;

    /**
     * @var ApplyExtensionsNormaliser
     */
    protected $applyExtensionsNormaliser;

    /**
     * @var TagNormaliser
     */
    protected $tagNormaliser;

    /**
     * @param ExtensionCallsNormaliser $extensionCallsNormaliser
     * @param NamespaceNormaliser $namespaceNormaliser
     * @param InheritanceNormaliser $inheritanceNormaliser
     * @param ApplyExtensionsNormaliser $applyExtensionsNormaliser
     * @param TagNormaliser $tagNormaliser
     */
    public function __construct(
        ExtensionCallsNormaliser $extensionCallsNormaliser,
        NamespaceNormaliser $namespaceNormaliser,
        InheritanceNormaliser $inheritanceNormaliser,
        ApplyExtensionsNormaliser $applyExtensionsNormaliser,
        TagNormaliser $tagNormaliser
    ) {
        $this->extensionCallsNormaliser = $extensionCallsNormaliser;
        $this->namespaceNormaliser = $namespaceNormaliser;
        $this->inheritanceNormaliser = $inheritanceNormaliser;
        $this->applyExtensionsNormaliser = $applyExtensionsNormaliser;
        $this->tagNormaliser = $tagNormaliser;
    }

    /**
     * @param array $definitions
     * @return array
     * @throws ReferenceException
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

        return [$this->tagNormaliser->normalise($definitions), $errors];
    }

}