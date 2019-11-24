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

use froq\validation\{Validation, ValidationException, RuleFail};

/**
 * Rule.
 * @package froq\validation
 * @object  froq\validation\Rule
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0, 4.0
 */
final class Rule
{
    /**
     * Field.
     * @var string
     */
    private string $field;

    /**
     * Field options.
     * @var array
     */
    private array $fieldOptions;

    /**
     * Fail.
     * @var array<int, string>|null
     */
    private array $fail;

    /**
     * Constructor.
     * @param string $field
     * @param array  $fieldOptions
     */
    public function __construct(string $field, array $fieldOptions)
    {
        if (empty($field)) {
            throw new ValidationException('Field name should not be empty');
        }
        if (empty($fieldOptions)) {
            throw new ValidationException('Field options should not be empty');
        }

        static $availableTypes = [
            Validation::TYPE_INT,    Validation::TYPE_FLOAT, Validation::TYPE_NUMERIC,
            Validation::TYPE_STRING, Validation::TYPE_BOOL,  Validation::TYPE_ENUM,
            Validation::TYPE_EMAIL,  Validation::TYPE_DATE,  Validation::TYPE_DATETIME,
            Validation::TYPE_URL
        ];

        @ ['type' => $type, 'spec' => $spec] = $fieldOptions;

        if ($type == null) {
            throw new ValidationException(sprintf(
                'Field "type" is not set in validation rules (field: %s)', $field
            ));
        } elseif (!in_array($type, $availableTypes)) {
            throw new ValidationException(sprintf(
                'Field "type" is not valid (field type: %s, available types: %s',
                $type, join(', ', $availableTypes)
            ));
        }

        // Check spec stuff.
        switch ($type) {
            case Validation::TYPE_BOOL:
            case Validation::TYPE_ENUM:
            case Validation::TYPE_DATE:
            case Validation::TYPE_DATETIME:
                if ($spec == null) {
                    throw new ValidationException(sprintf(
                        'Enum, bool, date and datetime types require "spec" definition (field: %s)',
                        $field
                    ));
                }
        }

        // Set spec type.
        if ($spec != null) {
            if (is_callable($spec)) {
                $fieldOptions['specType'] = 'callback';
            } else {
                $fieldOptions['specType'] = gettype($spec);

                if ($fieldOptions['specType'] != 'array' && in_array(
                    $type, [Validation::TYPE_BOOL, Validation::TYPE_ENUM]
                )) {
                    throw new ValidationException(sprintf(
                        'Invalid spec given, only an array accepted for enum types (field: %s)',
                        $field
                    ));
                }

                // Detect regexp spec.
                if ($fieldOptions['specType'] == 'string' && $spec[0] == '~') {
                    $fieldOptions['specType'] = 'regexp';
                }
            }
        }

        // Set other rules (eg: [foo => [type => int, ... [required, ...]]]).
        foreach ($fieldOptions as $key => $value) {
            if (is_int($key)) {
                // Drop used and non-valid items.
                unset($fieldOptions[$key]);

                if (in_array($value, ['required', 'unsigned', 'fixed'])) {
                    $fieldOptions[$value] = true;
                }
            }
        }

        // Check fixed limit.
        if (isset($fieldOptions['fixed']) && !isset($fieldOptions['limit'])) {
            throw new ValidationException('Option "limit" should not be empty when option "fixed" given');
        }

        $this->field = $field;
        $this->fieldOptions = $fieldOptions;
    }

    /**
     * Validate.
     * @param  scalar &$input
     * @return bool
     * @throws froq\validation\ValidationException
     */
    public function ok(&$input): bool
    {
        if (!is_scalar($input)) {
            throw new ValidationException('Only scalar types accepted for validation');
        }

        @ ['type' => $type, 'label' => $label, 'default' => $default,
           'limit' => $limit, 'limits' => $limits, 'spec' => $spec, 'specType' => $specType,
           'required' => $required, 'unsigned' => $unsigned, 'fixed' => $fixed] = $this->fieldOptions;

        $input = trim((string) $input);
        $inputLabel = trim($label ?? 'Field');

        // Callback spec overrides all rules.
        if ($specType == 'callback') {
            /** @var string|array $fail */
            $fail = null;

            if ($spec($input, $fail) === false) {
                $code = RuleFail::CALLBACK;
                $message = 'Callback returned false.';

                if (is_string($fail)) {
                    $message = $fail;
                } elseif (is_array($fail)) {
                    isset($fail['code']) && $code = $fail['code'];
                    isset($fail['message']) && $message = $fail['message'];
                }
                $this->toFail($code, $message);

                return false;
            }

            return true;
        }

        // Check required issue.
        if ($input === '' && $required) {
            $this->toFail(RuleFail::REQUIRED, sprintf('%s is required.', $inputLabel));

            return false;
        }

        // Assing default to input but do not return true to check also given default.
        if ($input === '') {
            $input = $default;
        }

        // Skip if null given as default that also checks given default.
        if ($input === null && !$required) {
            return true;
        }

        // Validate by type.
        switch ($type) {
            case Validation::TYPE_INT:
            case Validation::TYPE_FLOAT:
            case Validation::TYPE_NUMERIC:
                if (!is_numeric($input)) {
                    $this->toFail(RuleFail::TYPE,
                        sprintf('%s value must be type of %s.', $inputLabel, $type));

                    return false;
                }

                // Cast int/float.
                if ($type == Validation::TYPE_INT) {
                    $input = intval($input);
                } elseif ($type == Validation::TYPE_FLOAT) {
                    $input = floatval($input);
                }

                // Make unsigned.
                if ($unsigned) {
                    $input = abs($input);
                }

                // Check limit(s).
                if (isset($limit)) {
                    if (json_encode($input) <> json_encode($limit)) {
                        $this->toFail(RuleFail::NOT_EQUAL,
                            sprintf('%s value could be only %s.', $inputLabel, $limit));

                        return false;
                    }
                } elseif (isset($limits)) {
                    @ [$limitMin, $limitMax] = $limits;
                    if (isset($limitMin) && $input < $limitMin) {
                        $this->toFail(RuleFail::MINIMUM_VALUE,
                            sprintf('%s value could be minimum %s.', $inputLabel, $limitMin));

                        return false;
                    }
                    if (isset($limitMax) && $input > $limitMax) {
                        $this->toFail(RuleFail::MAXIMUM_VALUE,
                            sprintf('%s value could be maximum %s.', $inputLabel, $limitMax));

                        return false;
                    }
                }
                break;
            case Validation::TYPE_STRING:
                // Check regexp if provided.
                if ($specType == 'regexp' && !preg_match($spec, $input)) {
                    $this->toFail(RuleFail::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inputLabel));

                    return false;
                }

                $encoding = $this->fieldOptions['encoding'] ?? mb_internal_encoding();

                // This will pass checks below if 'fixed' option provided.
                if ($fixed) {
                    $input = mb_substr($input, 0, intval($limit), $encoding);
                    return true;
                }

                // Check limit(s).
                if (isset($limit)) {
                    $inputLength = mb_strlen($input, $encoding);
                    if ($inputLength <> $limit) {
                        $this->toFail(RuleFail::LENGTH,
                            sprintf('%s value length must be %s.', $inputLabel, $limit));

                        return false;
                    }
                } elseif (isset($limits)) {
                    @ [$limitMin, $limitMax, $inputLength] = [...$limits, mb_strlen($input, $encoding)];
                    if (isset($limitMin) && $inputLength < $limitMin) {
                        $this->toFail(RuleFail::MINIMUM_LENGTH,
                            sprintf('%s value minimum length could be %s.', $inputLabel, $limitMin));

                        return false;
                    }
                    if (isset($limitMax) && $inputLength > $limitMax) {
                        $this->toFail(RuleFail::MAXIMUM_LENGTH,
                            sprintf('%s value maximum length could be %s.', $inputLabel, $limitMax));

                        return false;
                    }
                }
                break;
            case Validation::TYPE_BOOL:
            case Validation::TYPE_ENUM:
                if (!in_array($input, $spec)) {
                    $this->toFail(RuleFail::NOT_FOUND,
                        sprintf('%s value could be one of %s options.', $inputLabel, join(', ', $spec)));

                    return false;
                }
                break;
            case Validation::TYPE_EMAIL:
                if ($specType == 'regexp' && !preg_match($spec, $input)) {
                    $this->toFail(RuleFail::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inputLabel));

                    return false;
                }

                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    $this->toFail(RuleFail::EMAIL,
                        sprintf('%s value must be a valid email address.', $inputLabel));

                    return false;
                }
                break;
            case Validation::TYPE_DATE:
            case Validation::TYPE_DATETIME:
                if ($specType == 'regexp' && !preg_match($spec, $input)) {
                    $this->toFail(RuleFail::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inputLabel));

                    return false;
                }

                if ($input != date($spec, strtotime($input))) {
                    $this->toFail(RuleFail::NOT_VALID,
                        sprintf('%s value is not a valid date/datetime.', $inputLabel));

                    return false;
                }
                break;
            case Validation::TYPE_URL:
                if ($specType == 'regexp' && !preg_match($spec, $input)) {
                    $this->toFail(RuleFail::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inputLabel));

                    return false;
                }

                if ($specType == 'array') {
                    // Remove silly empty components (eg: path always comes even url is empty).
                    $url = array_filter((array) parse_url($input), 'strlen');

                    $missingComponents = array_diff($spec, array_keys($url));
                    if ($missingComponents != null) {
                        $this->toFail(RuleFail::NOT_VALID,
                            sprintf('%s value is not a valid URL (missing components: %s).',
                                $inputLabel, join(', ', $missingComponents)));

                        return false;
                    }
                } elseif (!filter_var($input, FILTER_VALIDATE_URL)) {
                    $this->toFail(RuleFail::NOT_VALID,
                        sprintf('%s value is not a valid URL.', $inputLabel));

                    return false;
                }
                break;
        }

        // Seems all OK.
        return true;
    }

    /**
     * Get field.
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Get field options.
     * @return array
     */
    public function getFieldOptions(): array
    {
        return $this->fieldOptions;
    }

    /**
     * Get fail.
     * @return array
     */
    public function getFail(): array
    {
        return $this->fail;
    }

    /**
     * To fail.
     * @param  int    $code
     * @param  string $message
     * @return void
     */
    private function toFail(int $code, string $message): void
    {
        $this->fail = ['code' => $code, 'message' => $message];
    }
}
