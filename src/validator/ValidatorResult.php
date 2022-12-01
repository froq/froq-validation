<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
namespace froq\validation\validator;

/**
 * An internal class, used as validator result.
 *
 * @package froq\validation\validator
 * @class   froq\validation\validator\ValidatorResult
 * @author  Kerem Güneş
 * @since   6.0
 * @@internal
 */
class ValidatorResult
{
    /**
     * Constructor.
     *
     * @param array|null $error
     * @param bool|null  $dropped
     * @param bool|null  $returned
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
