<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation;

use froq\common\Exception;
use Throwable;

/**
 * Validation Exception.
 *
 * @package froq\validation
 * @object  froq\validation\ValidationException
 * @author  Kerem Güneş
 * @since   1.0
 */
class ValidationException extends Exception
{
    /** @var array */
    private array $errors;

    /**
     * Constructor.
     *
     * @param string|Throwable $message
     * @param any|null         $messageParams
     * @param int|null         $code
     * @param Throwable|null   $previous
     * @param array|null       $errors
     * @since 5.0
     */
    public function __construct(string|Throwable $message = null, $messageParams = null, int $code = null,
        Throwable $previous = null, array $errors = null)
    {
        if ($errors !== null) {
            $this->errors = $errors;
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
