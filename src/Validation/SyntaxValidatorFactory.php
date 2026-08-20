<?php

namespace Lexide\Syringe\Validation;

use Lexide\Syringe\Error\ErrorHelper;
use Lexide\Syringe\Reference\ReferenceHelper;

class SyntaxValidatorFactory
{

    protected ReferenceHelper $referenceHelper;
    protected ErrorHelper $errorHelper;
    protected TypeValidator $typeValidator;

    /**
     * @param ReferenceHelper $referenceHelper
     * @param ErrorHelper $errorHelper
     * @param TypeValidator $typeValidator
     */
    public function __construct(ReferenceHelper $referenceHelper, ErrorHelper $errorHelper, TypeValidator $typeValidator)
    {
        $this->referenceHelper = $referenceHelper;
        $this->typeValidator = $typeValidator;
        $this->errorHelper = $errorHelper;
    }

    /**
     * @param array $schemata
     * @return SyntaxValidator
     */
    public function create(array $schemata): SyntaxValidator
    {
        return new SyntaxValidator($this->referenceHelper, $this->errorHelper, $this->typeValidator, $schemata);
    }

}