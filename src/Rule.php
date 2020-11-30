<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\validation;

use froq\validation\{Validation, ValidationException, Failure};
use Closure;

/**
 * Rule.
 *
 * @package froq\validation
 * @object  froq\validation\Rule
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
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
     * @var ?array<int, string>
     */
    private ?array $fail = null;

    /**
     * Constructor.
     * @param string $field
     * @param array  $fieldOptions
     */
    public function __construct(string $field, array $fieldOptions)
    {
        if (empty($field)) {
            throw new ValidationException('Field name must not be empty');
        }
        if (empty($fieldOptions)) {
            throw new ValidationException('Field options must not be empty');
        }

        static $availableTypes = [
            Validation::TYPE_INT,      Validation::TYPE_FLOAT,    Validation::TYPE_NUMERIC,
            Validation::TYPE_STRING,   Validation::TYPE_BOOL,     Validation::TYPE_ENUM,
            Validation::TYPE_EMAIL,    Validation::TYPE_DATE,     Validation::TYPE_TIME,
            Validation::TYPE_DATETIME, Validation::TYPE_UNIXTIME, Validation::TYPE_JSON,
            Validation::TYPE_URL,      Validation::TYPE_UUID
        ];

        @ ['type' => $type, 'spec' => $spec] = $fieldOptions;

        if ($type && !in_array($type, $availableTypes)) {
            throw new ValidationException("Field 'type' is not valid (field type: %s, available types: %s",
                [$type, join(', ', $availableTypes)]);
        }

        // Check spec stuff.
        switch ($type) {
            case Validation::TYPE_BOOL:
            case Validation::TYPE_ENUM:
            case Validation::TYPE_DATE:
            case Validation::TYPE_DATETIME:
                if ($spec == null) {
                    throw new ValidationException("Enum, bool, date and datetime types require 'spec' definition "
                        . "(field: %s)", $field);
                }
                break;
        }

        // Set spec type.
        if ($spec != null) {
            if ($type == Validation::TYPE_JSON && !in_array($spec, ['array', 'object'])) {
                throw new ValidationException("Invalid spec given, only 'array' and 'object' accepted for json "
                    . "types (field: %s)", $field);
            } elseif ($spec instanceof Closure) {
                $fieldOptions['specType'] = 'callback';
            } else {
                $fieldOptions['specType'] = gettype($spec);

                if ($fieldOptions['specType'] != 'array' && in_array(
                    $type, [Validation::TYPE_BOOL, Validation::TYPE_ENUM]
                )) {
                    throw new ValidationException("Invalid spec given, only an array accepted for bool and enum "
                        . "types (field: %s)", $field);
                }

                // Detect regexp spec.
                if ($fieldOptions['specType'] == 'string' && $spec[0] == '~') {
                    $fieldOptions['specType'] = 'regexp';
                }
            }
        }

        // Set other rules (eg: [foo => [type => int, ..., required, ...]]).
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
            throw new ValidationException("Option 'limit' must not be empty when option 'fixed' given");
        }

        $this->field = $field;
        $this->fieldOptions = $fieldOptions;
    }

    /**
     * Validate.
     * @param  scalar      &$in
     * @param  string|null  $inLabel
     * @return bool
     * @throws froq\validation\ValidationException
     */
    public function ok(&$in, string $inLabel = null): bool
    {
        @ ['type' => $type, 'label' => $label, 'default' => $default,
           'limit' => $limit, 'limits' => $limits, 'spec' => $spec, 'specType' => $specType,
           'required' => $required, 'unsigned' => $unsigned, 'fixed' => $fixed, 'filter' => $filter,
          ] = $this->fieldOptions;

        // Apply filter first if provided.
        if ($filter && is_callable($filter)) {
            $in = $filter($in);
        }

        if (isset($in) && !is_scalar($in)) {
            throw new ValidationException("Only scalar types accepted for validation, '%s' given", gettype($in));
        }

        $in = is_string($in) ? trim($in) : $in;
        $inLabel = trim($label ?? ($inLabel ? 'Field "' . $inLabel . '"' : 'Field'));

        // Callback spec overrides all rules.
        if ($specType == 'callback') {
            /** @var string|array $fail */
            $fail = null;

            if ($spec($in, $fail) === false) {
                $code = Failure::CALLBACK;
                $message = sprintf("Callback returned false for '%s'.", $inLabel);

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
        if ($required && ($in === '' || $in === null)) {
            $this->toFail(Failure::REQUIRED, sprintf('%s is required.', $inLabel));

            return false;
        }

        // Assing default to input but do not return true to check also given default.
        if ($in === '') {
            $in = $default;
        }

        // Skip if null given as default that also checks given default.
        if (!$required && $in === null) {
            return true;
        }

        // Validate by type.
        switch ($type) {
            case Validation::TYPE_ENUM:
                if (!in_array($in, $spec, true)) {
                    $this->toFail(Failure::NOT_FOUND,
                        sprintf('%s value must be one of %s options.', $inLabel, join(', ', $spec)));

                    return false;
                }
                break;
            case Validation::TYPE_INT:
            case Validation::TYPE_FLOAT:
            case Validation::TYPE_NUMERIC:
                if (!is_numeric($in)) {
                    $this->toFail(Failure::TYPE,
                        sprintf('%s value must be type of %s.', $inLabel, $type));

                    return false;
                }

                // Cast int/float.
                if ($type == Validation::TYPE_INT) {
                    $in = intval($in);
                } elseif ($type == Validation::TYPE_FLOAT) {
                    $in = floatval($in);
                }

                // Make unsigned.
                if ($unsigned) {
                    $in = abs($in);
                }

                // Check limit(s).
                if (isset($limit)) {
                    if (json_encode($in) <> json_encode($limit)) {
                        $this->toFail(Failure::NOT_EQUAL,
                            sprintf('%s value must be only %s.', $inLabel, $limit));

                        return false;
                    }
                } elseif (isset($limits)) {
                    @ [$limitMin, $limitMax] = $limits;
                    if (isset($limitMin) && $in < $limitMin) {
                        $this->toFail(Failure::MINIMUM_VALUE,
                            sprintf('%s value must be minimum %s.', $inLabel, $limitMin));

                        return false;
                    }
                    if (isset($limitMax) && $in > $limitMax) {
                        $this->toFail(Failure::MAXIMUM_VALUE,
                            sprintf('%s value must be maximum %s.', $inLabel, $limitMax));

                        return false;
                    }
                }
                break;
            case Validation::TYPE_STRING:
                // Check regexp if provided.
                if ($specType == 'regexp' && !preg_match($spec, $in)) {
                    $this->toFail(Failure::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inLabel));

                    return false;
                }

                $encoding = $this->fieldOptions['encoding'] ?? mb_internal_encoding();

                // Make fixed.
                if ($fixed) {
                    $in = mb_substr($in, 0, intval($limit ?? mb_strlen($in, $encoding)), $encoding);
                }

                // Check limit(s).
                if (isset($limit)) {
                    $inLength = mb_strlen($in, $encoding);
                    if ($inLength <> $limit) {
                        $this->toFail(Failure::LENGTH,
                            sprintf('%s value length must be %s.', $inLabel, $limit));

                        return false;
                    }
                } elseif (isset($limits)) {
                    @ [$limitMin, $limitMax, $inLength] = [...$limits, mb_strlen($in, $encoding)];
                    if (isset($limitMin) && $inLength < $limitMin) {
                        $this->toFail(Failure::MINIMUM_LENGTH,
                            sprintf('%s value minimum length must be %s.', $inLabel, $limitMin));

                        return false;
                    }
                    if (isset($limitMax) && $inLength > $limitMax) {
                        $this->toFail(Failure::MAXIMUM_LENGTH,
                            sprintf('%s value maximum length must be %s.', $inLabel, $limitMax));

                        return false;
                    }
                }
                break;
            case Validation::TYPE_BOOL:
                if (!in_array($in, $spec, true)) {
                    $this->toFail(Failure::NOT_FOUND,
                        sprintf('%s value must be one of %s options.', $inLabel, join(', ', $spec)));

                    return false;
                }
                break;
            case Validation::TYPE_EMAIL:
                if ($specType == 'regexp' && !preg_match($spec, $in)) {
                    $this->toFail(Failure::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inLabel));

                    return false;
                }

                if (!filter_var($in, FILTER_VALIDATE_EMAIL)) {
                    $this->toFail(Failure::EMAIL,
                        sprintf('%s value must be a valid email address.', $inLabel));

                    return false;
                }
                break;
            case Validation::TYPE_DATE:
            case Validation::TYPE_TIME:
            case Validation::TYPE_DATETIME:
                if ($specType == 'regexp' && !preg_match($spec, $in)) {
                    $this->toFail(Failure::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inLabel));

                    return false;
                }

                $date = date_create_from_format($spec, $in);
                if (!$date || $date->format($spec) !== $in) {
                    $this->toFail(Failure::NOT_VALID,
                        sprintf('%s value is not a valid date/time/datetime.', $inLabel));

                    return false;
                }
                break;
            case Validation::TYPE_UNIXTIME:
                $inString = (string) $in;
                if (!ctype_digit($inString) || strlen($inString) != strlen((string) time())) {
                    $this->toFail(Failure::NOT_VALID,
                        sprintf('%s value is not a valid unixtime.', $inLabel));

                    return false;
                }
                break;
            case Validation::TYPE_URL:
                if ($specType == 'regexp' && !preg_match($spec, $in)) {
                    $this->toFail(Failure::NOT_MATCH,
                        sprintf('%s value did not match with given pattern.', $inLabel));

                    return false;
                }

                if ($specType == 'array') {
                    // Remove silly empty components (eg: path always comes even url is empty).
                    $url = array_filter((array) parse_url($in), 'strlen');

                    $missingComponents = array_diff($spec, array_keys($url));
                    if ($missingComponents != null) {
                        $this->toFail(Failure::NOT_VALID,
                            sprintf('%s value is not a valid URL (missing components: %s).',
                                $inLabel, join(', ', $missingComponents)));

                        return false;
                    }
                } elseif (!filter_var($in, FILTER_VALIDATE_URL)) {
                    $this->toFail(Failure::NOT_VALID,
                        sprintf('%s value is not a valid URL.', $inLabel));

                    return false;
                }
                break;
            case Validation::TYPE_UUID:
                $dash = $this->fieldOptions['dash'] ?? true;
                if (!$dash ? !preg_match('~^[a-f0-9]{32}$~', $in)
                           : !preg_match('~^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~', $in)
                ) {
                    $this->toFail(Failure::NOT_VALID,
                        sprintf('%s value is not a valid UUID.', $inLabel));

                    return false;
                }
                break;
            case Validation::TYPE_JSON:
                // Validates JSON array/object inputs only.
                if ($spec) {
                    $chars = ($in[0] ?? '') . ($in[-1] ?? '');
                    if ($spec == 'array' && $chars !== '[]') {
                        $this->toFail(Failure::NOT_VALID,
                            sprintf('%s value is not a valid JSON array.', $inLabel));

                        return false;
                    } elseif ($spec == 'object' && $chars !== '{}') {
                        $this->toFail(Failure::NOT_VALID,
                            sprintf('%s value is not a valid JSON object.', $inLabel));

                        return false;
                    }
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
     * @return ?array
     */
    public function getFail(): ?array
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
