<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation\validator;

use froq\validation\ValidationError;

/**
 * @package froq\validation\validator
 * @object  froq\validation\validator\ArrayValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class ArrayValidator extends Validator
{
    /**
     * @inheritDoc froq\validation\validator\Validator
     */
    public function validate(): ValidatorResult
    {
        $this->prepare();

        if ($this->result->isDropped()
            || $this->result->isReturned()) {
            return $this->result;
        }

        if (!$this->isType('array')) {
            $this->result->error = $this->error(
                ValidationError::TYPE,
                '%s value must be array, %t given.',
                $this->inputLabel, $this->input
            );
        }

        return $this->result;
    }
}
