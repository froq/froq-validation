<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
namespace froq\validation;

/**
 * @package froq\validation
 * @class   froq\validation\ValidationError
 * @author  Kerem Güneş
 * @since   4.0, 4.3, 5.0
 */
class ValidationError extends \froq\common\Error
{
    /** Error codes. */
    public const CALLBACK   = 1,  REQUIRED   = 2,  TYPE       = 3,  LENGTH     = 4,
                 EMAIL      = 5,  ENUM       = 6,  NOT_EQUAL  = 7,  NOT_VALID  = 8,
                 NOT_MATCH  = 9,  MIN_VALUE  = 10, MAX_VALUE  = 11, MIN_LENGTH = 12,
                 MAX_LENGTH = 13;

    /** Errors. */
    private ?array $errors = null;

    /**
     * Constructor.
     *
     * @param string|Throwable|null $message
     * @param mixed|null            $messageParams
     * @param int|null              $code
     * @param array|null            $errors
     * @since 5.0
     */
    public function __construct(string|\Throwable $message = null, mixed $messageParams = null, int $code = null,
        array $errors = null)
    {
        if ($errors !== null) {
            $this->errors = $errors;
        } elseif ($message !== null || $code !== null) {
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
    public function errors(): array|null
    {
        return $this->errors;
    }

    /**
     * Get tip.
     *
     * @return string
     * @since  6.0
     */
    public static function tip(): string
    {
        return 'use a try/catch block and call errors() to see error details';
    }
}
