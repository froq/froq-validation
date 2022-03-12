<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation\validator;

/**
 * Validator Result.
 *
 * @package froq\validation\validator
 * @object  froq\validation\validator\ValidatorResult
 * @author  Kerem Güneş
 * @since   6.0
 * @@internal
 */
class ValidatorResult
{
    /**
     * Constructor.
     */
    public function __construct(
        public ?array $error    = null,
        public ?bool  $dropped  = null,
        public ?bool  $returned = null,
    ) {}

    /**
     * Signal for dropping input field by "drop" option.
     *
     * @return bool
     */
    public function isDropped(): bool
    {
        return ($this->dropped === true);
    }

    /**
     * Signal for result error by "required" option.
     *
     * @return bool
     */
    public function isReturned(): bool
    {
        return ($this->returned !== null);
    }
}
