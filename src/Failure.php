<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\validation;

/**
 * Failure.
 *
 * @package froq\validation
 * @object  froq\validation\Failure
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0, 4.3 Renamed from RuleFail.
 * @static
 */
final class Failure
{
    /**
     * Failures.
     * @const int
     */
    public const CALLBACK       = 1,
                 REQUIRED       = 2,
                 TYPE           = 3,
                 LENGTH         = 4,
                 EMAIL          = 5,
                 NOT_EQUAL      = 6,
                 NOT_FOUND      = 7,
                 NOT_VALID      = 8,
                 NOT_MATCH      = 9,
                 MINIMUM_VALUE  = 10,
                 MAXIMUM_VALUE  = 11,
                 MINIMUM_LENGTH = 12,
                 MAXIMUM_LENGTH = 13;
}
