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
 * @object  froq\validation\validator\UnixTimeValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class UnixTimeValidator extends Validator
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

        if (!$this->isType('numeric')) {
            $this->result->error = $this->error(
                ValidationError::TYPE,
                '%s value must be numeric, %t given.',
                $this->inputLabel, $this->input
            );
        } else {
            [$accept, $strict] = $this->getOptions(['accept', 'strict']);

            // Check accepted stuff for special cases (eg: 0, -1).
            if ($accept !== null && in_array($this->input, (array) $accept, (bool) $strict)) {
                // Pass.
            } else {
                $stringInput = (string) $this->input;
                if (!ctype_digit($stringInput) || strlen($stringInput) != strlen((string) time())) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value is not a valid unixtime.',
                        $this->inputLabel
                    );

                    return $this->result;
                }
            }

            // Always cast as int.
            $this->input = (int) $this->input;
        }

        return $this->result;
    }
}