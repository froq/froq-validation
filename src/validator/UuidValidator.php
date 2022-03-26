<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation\validator;

use froq\validation\ValidationError;

/**
 * Uuid Validator.
 *
 * @package froq\validation\validator
 * @object  froq\validation\validator\UuidValidator
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
            [$spec, $specType] = $this->getOptions(['spec', 'specType']);

            if ($spec && $specType == 'regexp') {
                if (!$this->isMatch($spec)) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.',
                        $this->inputLabel
                    );
                }
            } else {
                [$null, $dashed, $cased] = $this->getOptions(['null', 'dashed', 'cased']);

                // Accept null UUIDs? @default=false
                if (!$null && $this->checkNull()) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value is not a valid UUID, null UUID given.',
                        $this->inputLabel
                    );
                } else {
                    isset($dashed) && $dashed = (bool) $dashed;
                    isset($cased)  && $cased  = (bool) $cased;

                    $pattern = $this->getPattern($dashed, $cased);

                    if (!$this->isMatch($pattern)) {
                        $this->result->error = $this->error(
                            ValidationError::NOT_VALID,
                            '%s value is not a valid UUID.',
                            $this->inputLabel
                        );
                    }
                }
            }
        }

        return $this->result;
    }

    /**
     * Check null input.
     */
    private function checkNull(): bool
    {
        return ($this->input === '00000000000000000000000000000000')
            || ($this->input === '00000000-0000-0000-0000-000000000000');
    }

    /**
     * Get pattern by options.
     */
    private function getPattern(?bool $dashed, ?bool $cased): string
    {
        // Accept non-dashed UUIDs or both? @default=both
        $pattern = match ($dashed) {
            true    => '~^(?:[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12})$~',
            false   => '~^(?:[A-F0-9]{32})$~',
            default => '~^(?:[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}|[A-F0-9]{32})$~'
        };

        // Case insensitive?
        $cased || $pattern .= 'i';

        return $pattern;
    }
}
