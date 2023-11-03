<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
namespace froq\validation\validator;

use froq\validation\ValidationError;

/**
 * @package froq\validation\validator
 * @class   froq\validation\validator\UuidValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class UuidValidator extends Validator
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

        if (!$this->isType('string')) {
            $this->result->error = $this->error(
                ValidationError::TYPE,
                '%s value must be string, %t given.',
                $this->inputLabel, $this->input
            );
        } else {
            [$spec, $specType] = $this->getOptions(['spec', 'specType']);

            if ($spec && $specType === 'regexp') {
                if (!$this->isMatch($spec)) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.',
                        $this->inputLabel
                    );
                }
            } else {
                [$null, $strict] = $this->getOptions(['null', 'strict']);

                $uuid = new \Uuid($this->input);

                // Accept null UUIDs? @default=false
                if (!$null && ($uuid->isNull() || $uuid->isNullHash())) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value is not a valid UUID, null UUID given.',
                        $this->inputLabel
                    );
                } elseif (!$uuid->isValid((bool) $strict)) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value is not a valid UUID.',
                        $this->inputLabel
                    );
                }
            }
        }

        return $this->result;
    }
}
