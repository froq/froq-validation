<?php
/**
 * MIT License <https://opensource.org/licenses/mit>
 *
 * Copyright (c) 2015 Kerem Güneş
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
declare(strict_types=1);

namespace froq\validation;

/**
 * Rule Fail.
 * @package froq\validation
 * @object  froq\validation\RuleFail
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.0
 * @static
 */
final class RuleFail
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
