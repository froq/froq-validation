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
 * @object  froq\validation\validator\CallbackValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class CallbackValidator extends Validator
{
    /**
     * @inheritDoc froq\validation\validator\Validator
     */
    public function validate(array &$data = null): ValidatorResult
    {
        $this->prepare();

        [$cast, $spec] = $this->getOptions(['cast', 'spec']);

        // Apply cast if given.
        if ($cast) {
            // Use type option if a true-like option given.
            if (!is_string($cast)) {
                $cast = $this->options['type'];
            }

            settype($this->input, $cast);
        }

        /** @var string|array (byref) */
        $error = null;

        if ($spec($this->input, $data, $error) === false) {
            $code    = ValidationError::CALLBACK;
            $message = format(
                'Callback returned false for field %q.',
                $this->options['name']
            );

            // Update error if callback changes it (by-ref).
            if (is_string($error)) {
                $message = $error;
            } elseif (is_array($error)) {
                isset($error['code'])    && $code    = $error['code'];
                isset($error['message']) && $message = $error['message'];
            }

            $this->result->error = $this->error($code, $message);
        }

        return $this->result;
    }
}
