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
 * @object  froq\validation\validator\DateTimeValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class DateTimeValidator extends Validator
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
            [$spec, $specType, $type] = $this->getOptions(['spec', 'specType', 'type']);

            if ($spec && $specType == 'regexp') {
                if (!$this->isMatch($spec)) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.',
                        $this->inputLabel
                    );
                }
            } else {
                $date = date_create_from_format($spec, $this->input);

                if (!$date || $date->format($spec) != $this->input) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value is not a valid %s.',
                        $this->inputLabel, $type
                    );
                }
            }
        }

        return $this->result;
    }
}
