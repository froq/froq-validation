<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation;

use froq\common\Error;
use Throwable;

/**
 * Validation Error.
 *
 * @package froq\validation
 * @object  froq\validation\ValidationError
 * @author  Kerem Güneş
 * @since   4.0, 4.3 Replaced with RuleFail, 5.0 Added errors stuff.
 * @static
 */
class ValidationError extends Error
{
    /**
     * Error types (codes).
     * @const int
     */
    public const CALLBACK   = 1,
                 REQUIRED   = 2,
                 TYPE       = 3,
                 LENGTH     = 4,
                 EMAIL      = 5,
                 NOT_EQUAL  = 6,
                 NOT_FOUND  = 7,
                 NOT_VALID  = 8,
                 NOT_MATCH  = 9,
                 MIN_VALUE  = 10,
                 MAX_VALUE  = 11,
                 MIN_LENGTH = 12,
                 MAX_LENGTH = 13;

    /** @var array */
    private array $errors;

    /**
     * Constructor.
     *
     * @param string|Throwable $message
     * @param any|null         $messageParams
     * @param int|null         $code
     * @param array|null       $errors
     * @param Throwable|null   $previous
     * @since 5.0
     */
    public function __construct(string|Throwable $message = null, $messageParams = null, int $code = null,
        array $errors = null, Throwable $previous = null)
    {
        if ($errors !== null) {
            $this->errors = $errors;
        } elseif ($code !== null || $message !== null) {
            // For instance throws.
            if ($message && $messageParams) {
                $message = vsprintf($message, (array) $messageParams);
            }

            $this->errors = ['code' => $code, 'message' => $message];
        }

        parent::__construct($message, $messageParams, $code, $previous);
    }

    /**
     * Get errors.
     *
     * @return array|null
     * @since  5.0
     */
    public final function errors(): array|null
    {
        return $this->errors ?? null;
    }
}
