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
 * @object  froq\validation\validator\JsonValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class JsonValidator extends Validator
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

        if (!$this->isType('string')) {
            $this->result->error = $this->error(
                ValidationError::TYPE,
                '%s value must be string, %t given.',
                $this->inputLabel, $this->input
            );
        } else {
            $spec = $this->getOption('spec');

            // Validates array/object inputs only when spec given.
            if ($spec) {
                $wrap = ($this->input[0] ?? '')
                      . ($this->input[-1] ?? '');

                if ($spec == 'array' && $wrap != '[]') {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value is not a valid JSON array.',
                        $this->inputLabel
                    );

                    return $this->result;
                } elseif ($spec == 'object' && $wrap != '{}') {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value is not a valid JSON object.',
                        $this->inputLabel
                    );

                    return $this->result;
                }
            }

            // Try real validation.
            if (!json_validate($this->input)) {
                $this->result->error = $this->error(
                    ValidationError::NOT_VALID,
                    '%s value is not a valid JSON input.',
                    $this->inputLabel
                );
            }
        }

        return $this->result;
    }
}
