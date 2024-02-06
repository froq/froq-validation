<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
namespace froq\validation\validator;

use froq\validation\ValidationError;

/**
 * @package froq\validation\validator
 * @class   froq\validation\validator\AnyValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class AnyValidator extends Validator
{
    /**
     * @inheritDoc froq\validation\validator\Validator
     */
    public function validate(): ValidatorResult
    {
        $this->prepare();

        if ($this->result->isDropped() || $this->result->isReturned()) {
            return $this->result;
        }

        return $this->result;
    }
}
