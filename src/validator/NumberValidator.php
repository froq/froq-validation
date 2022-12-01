<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
namespace froq\validation\validator;

use froq\validation\{ValidationType, ValidationError};

/**
 * @package froq\validation\validator
 * @class   froq\validation\validator\NumberValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class NumberValidator extends Validator
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

        $type = $this->getOption('type');

        if (!$this->checkType($type)) {
            $this->result->error = $this->error(
                ValidationError::TYPE,
                '%s value must be %s, %t given.',
                $this->inputLabel, $type, $this->input
            );
        } else {
            // Cast int/float.
            $this->input = match ($type) {
                ValidationType::INT   => (int) $this->input,
                ValidationType::FLOAT => (float) $this->input,
                default               => $this->input += 0
            };

            [$unsigned, $precision, $equal] = $this->getOptions(['unsigned', 'precision', 'equal']);

            // Make unsigned & update precision.
            $unsigned && $this->input = abs($this->input);
            $precision !== null && $this->input = round($this->input, $precision);

            if ($equal !== null) {
                if (!$this->checkEqual($equal)) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_EQUAL,
                        '%s value must be equal to %s.',
                        $this->inputLabel, $equal
                    );
                }
            } else {
                [$min, $max, $range] = $this->getOptions(['min', 'max', 'range']);

                if ($range !== null && ($this->input < $range[0] || $this->input > $range[1])) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_VALID,
                        '%s value must be between %s and %s.',
                        $this->inputLabel, $range[0], $range[1]
                    );
                } elseif ($min !== null && $this->input < $min) {
                    $this->result->error = $this->error(
                        ValidationError::MIN_VALUE,
                        '%s value must be minimum %s.',
                        $this->inputLabel, $min
                    );
                } elseif ($max !== null && $this->input > $max) {
                    $this->result->error = $this->error(
                        ValidationError::MAX_VALUE,
                        '%s value must be maximum %s.',
                        $this->inputLabel, $max
                    );
                }
            }
        }

        return $this->result;
    }

    /**
     * Check input type.
     */
    private function checkType(string $type): bool
    {
        // Function (eg: is_int, is_float, is_number or is_numeric).
        $check = 'is_' . ($this->getOption('strict') ? $type : 'numeric');

        return $check($this->input);
    }

    /**
     * Check input equality.
     */
    private function checkEqual(mixed $input): bool
    {
        return var_export($input, true) === var_export($this->input, true);
    }
}
