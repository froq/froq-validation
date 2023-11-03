<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
namespace froq\validation\validator;

use froq\validation\ValidationError;

/**
 * @package froq\validation\validator
 * @class   froq\validation\validator\EnumValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class EnumValidator extends Validator
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

        [$cast, $spec, $strict] = $this->getOptions(['cast', 'spec', 'strict']);

        // Apply cast if given.
        $cast && settype($this->input, $cast);

        // Always strict if not given.
        $strict = ($strict ?? true);

        if (!in_array($this->input, (array) $spec, (bool) $strict)) {
            $this->result->error = $this->error(
                ValidationError::ENUM,
                '%s value must be one of these options: %A.',
                $this->inputLabel, $spec
            );
        }

        return $this->result;
    }
}
