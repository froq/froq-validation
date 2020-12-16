<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\validation;

use froq\validation\{Validation, ValidationError, ValidationException};
use Closure;

/**
 * Rule.
 *
 * Represents a rule entity which accepts a field & field options and is able to validate given field input
 * by its options, filling `$error` property with last occured error.
 *
 * @package froq\validation
 * @object  froq\validation\Rule
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
final class Rule
{
    /** @var string */
    private string $field;

    /** @var array */
    private array $fieldOptions;

    /** @var ?array<int, string> */
    private array $error;

    /** @var array */
    private static array $availableTypes = [
        Validation::TYPE_INT,      Validation::TYPE_FLOAT,    Validation::TYPE_NUMERIC,
        Validation::TYPE_STRING,   Validation::TYPE_BOOL,     Validation::TYPE_ENUM,
        Validation::TYPE_EMAIL,    Validation::TYPE_DATE,     Validation::TYPE_TIME,
        Validation::TYPE_DATETIME, Validation::TYPE_UNIXTIME, Validation::TYPE_JSON,
        Validation::TYPE_URL,      Validation::TYPE_UUID
    ];

    /** @var array */
    private static array $specableTypes = [
        Validation::TYPE_BOOL, Validation::TYPE_ENUM,
        Validation::TYPE_DATE, Validation::TYPE_DATETIME
    ];

    /**
     * Constructor.
     *
     * @param string $field
     * @param array  $fieldOptions
     */
    public function __construct(string $field, array $fieldOptions)
    {
        $field || throw new ValidationException('Field name must not be empty');
        $fieldOptions || throw new ValidationException('Field options must not be empty');

        [$type, $spec] = array_select($fieldOptions, ['type', 'spec']);

        if ($type != null) {
            if (!equals($type, ...self::$availableTypes)) {
                throw new ValidationException('Field `type` is not valid (field type: %s, available types: %s)',
                    [$type, join(', ', self::$availableTypes)]);
            } elseif ($spec == null && equals($type, ...self::$specableTypes)) {
                throw new ValidationException('Types %s require `spec` definition in options (field: %s)',
                    [join(', ', self::$specableTypes), $field]);
            }
        }

        // Set spec type.
        if ($spec != null) {
            if ($type === Validation::TYPE_JSON && !equals($spec, 'array', 'object')) {
                throw new ValidationException('Invalid spec given, only `array` and `object` accepted for json '
                    . 'types (field: %s)', $field);
            } elseif ($spec instanceof Closure) {
                $fieldOptions['specType'] = 'callback';
            } else {
                $fieldOptions['specType'] = gettype($spec);

                if ($fieldOptions['specType'] !== 'array'
                    && equals($type, Validation::TYPE_BOOL, Validation::TYPE_ENUM)) {
                    throw new ValidationException('Invalid spec given, only an array accepted for bool and enum '
                        . 'types (field: %s)', $field);
                }

                // Detect regexp spec.
                if ($fieldOptions['specType'] === 'string' && $spec[0] === '~') {
                    $fieldOptions['specType'] = 'regexp';
                }
            }
        }

        // Set other rules (eg: [foo => [type => int, ..., required, ...]]).
        foreach ($fieldOptions as $key => $value) {
            if (is_int($key)) {
                // Drop used and non-valid items.
                unset($fieldOptions[$key]);

                if (equals($value, 'required', 'unsigned', 'fixed')) {
                    $fieldOptions[$value] = true;
                }
            }
        }

        // Check fixed limit.
        if (isset($fieldOptions['fixed']) && !isset($fieldOptions['limit'])) {
            throw new ValidationException('Option `limit` must not be empty when option `fixed` given');
        }

        $this->field = $field;
        $this->fieldOptions = $fieldOptions;
    }

    /**
     * Get field property.
     *
     * @return string
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * Get field-options property.
     *
     * @return array
     */
    public function fieldOptions(): array
    {
        return $this->fieldOptions;
    }

    /**
     * Get error property.
     *
     * @return array|null
     */
    public function error(): array|null
    {
        return $this->error ?? null;
    }

    /**
     * Validate given input, sanitizing/modifying it to declared type.
     *
     * @param  scalar      &$in
     * @param  string|null  $inLabel
     * @return bool
     * @throws froq\validation\ValidationException
     */
    public function okay(&$in, string $inLabel = null): bool
    {
        [$type, $label, $default, $limit, $limits, $spec, $specType, $required, $unsigned, $fixed, $filter]
            = array_select($this->fieldOptions, ['type', 'label', 'default', 'limit', 'limits', 'spec', 'specType',
                'required', 'unsigned', 'fixed', 'filter']);

        // Apply filter first if provided.
        if ($filter && is_callable($filter)) {
            $in = $filter($in);
        }

        if (isset($in) && !is_scalar($in)) {
            throw new ValidationException('Only scalar types accepted for validation, `%s` given',
                get_type($in));
        }

        $in = is_string($in) ? trim($in) : $in;
        $inLabel = trim($label ?? ($inLabel ? 'Field `' . $inLabel . '`' : 'Field'));

        // Callback spec overrides all rules.
        if ($specType === 'callback') {
            /** @var string|array */
            $error = null;

            if ($spec($in, $error) === false) {
                $code = ValidationError::CALLBACK;
                $message = sprintf('Callback returned false for `%s` field.', $inLabel);

                if (is_string($error)) {
                    $message = $error;
                } elseif (is_array($error)) {
                    isset($error['code']) && $code = $error['code'];
                    isset($error['message']) && $message = $error['message'];
                }

                $this->toError($code, $message);

                return false;
            }

            return true;
        }

        // Check required issue.
        if ($required && ($in === '' || $in === null)) {
            return $this->toError(ValidationError::REQUIRED, '%s is required.', $inLabel);
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
            case Validation::TYPE_ENUM: {
                if (!in_array($in, $spec, true)) {
                    return $this->toError(ValidationError::NOT_FOUND,
                        '%s value must be one of %s options.', [$inLabel, join(', ', $spec)]);
                }

                return true;
            }
            case Validation::TYPE_INT:
            case Validation::TYPE_FLOAT:
            case Validation::TYPE_NUMERIC: {
                if (!is_numeric($in)) {
                    return $this->toError(ValidationError::TYPE,
                        '%s value must be type of %s, %s given.', [$inLabel, $type, get_type($in)]);
                }

                // Cast int/float.
                if ($type === Validation::TYPE_INT) {
                    $in = intval($in);
                } elseif ($type === Validation::TYPE_FLOAT) {
                    $in = floatval($in);
                }

                // Make unsigned.
                $unsigned && $in = abs($in);

                // Check limit(s).
                if (isset($limit)) {
                    if (json_encode($in) <> json_encode($limit)) {
                        return $this->toError(ValidationError::NOT_EQUAL,
                            '%s value must be only %s.', [$inLabel, $limit]);
                    }
                } elseif (isset($limits)) {
                    @ [$limitMin, $limitMax] = (array) $limits;
                    if (isset($limitMin) && $in < $limitMin) {
                        return $this->toError(ValidationError::MIN_VALUE,
                            '%s value must be minimum %s.', [$inLabel, $limitMin]);
                    }
                    if (isset($limitMax) && $in > $limitMax) {
                        return $this->toError(ValidationError::MAX_VALUE,
                            '%s value must be maximum %s.', [$inLabel, $limitMax]);
                    }
                }

                return true;
            }
            case Validation::TYPE_STRING: {
                if (!is_string($in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value must be string, %s given.', [$inLabel, get_type($in)]);
                }

                // Check regexp if provided.
                if ($specType === 'regexp' && !preg_match($spec, $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.', $inLabel);
                }

                $encoding = $this->fieldOptions['encoding'] ?? null;

                // Crop.
                $fixed && $in = mb_substr($in, 0, (int) $limit, $encoding);

                // Check limit(s).
                if (isset($limit)) {
                    if (mb_strlen($in, $encoding) <> $limit) {
                        return $this->toError(ValidationError::LENGTH,
                            '%s value length must be %s.', [$inLabel, $limit]);
                    }
                } elseif (isset($limits)) {
                    @ [$limitMin, $limitMax] = (array) $limits;
                    if (isset($limitMin) && mb_strlen($in, $encoding) < $limitMin) {
                        return $this->toError(ValidationError::MIN_LENGTH,
                            '%s value minimum length must be %s.', [$inLabel, $limitMin]);
                    }
                    if (isset($limitMax) && mb_strlen($in, $encoding) > $limitMax) {
                        return $this->toError(ValidationError::MAX_LENGTH,
                            '%s value maximum length must be %s.', [$inLabel, $limitMax]);
                    }
                }

                return true;
            }
            case Validation::TYPE_BOOL: {
                if (!in_array($in, $spec, true)) {
                    return $this->toError(ValidationError::NOT_FOUND,
                        '%s value must be one of %s options.', [$inLabel, join(', ', $spec)]);
                }

                return true;
            }
            case Validation::TYPE_EMAIL: {
                if ($specType === 'regexp' && !preg_match($spec, (string) $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.', $inLabel);
                }
                if (!filter_var($in, FILTER_VALIDATE_EMAIL)) {
                    return $this->toError(ValidationError::EMAIL,
                        '%s value must be a valid email address.', $inLabel);
                }

                return true;
            }
            case Validation::TYPE_DATE:
            case Validation::TYPE_TIME:
            case Validation::TYPE_DATETIME: {
                if ($specType === 'regexp' && !preg_match($spec, (string) $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.', $inLabel);
                }

                $date = date_create_from_format((string) $spec, (string) $in);
                if (!$date || $date->format($spec) !== $in) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid date/time/datetime.', $inLabel);
                }

                return true;
            }
            case Validation::TYPE_UNIXTIME: {
                [$in, $inString] = [(int) $in, (string) $in];
                if (!ctype_digit($inString) || strlen($inString) !== strlen((string) time())) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid Unixtime.', $inLabel);
                }

                return true;
            }
            case Validation::TYPE_URL: {
                if ($specType === 'regexp' && !preg_match($spec, (string) $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.', $inLabel);
                }

                if ($specType === 'array') {
                    // Remove silly empty components (eg: path always comes even url is empty).
                    $url = array_filter((array) parse_url((string) $in), 'strlen');

                    $missingComponents = array_diff($spec, array_keys($url));
                    if ($missingComponents) {
                        return $this->toError(ValidationError::NOT_VALID,
                            '%s value is not a valid URL (missing components: %s).',
                                [$inLabel, join(', ', $missingComponents)]);
                    }
                } elseif (!filter_var($in, FILTER_VALIDATE_URL)) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid URL.', $inLabel);
                }

                return true;
            }
            case Validation::TYPE_UUID: {
                $null = $this->fieldOptions['null'] ?? false;
                if (!$null && ($in === '00000000000000000000000000000000' ||
                               $in === '00000000-0000-0000-0000-000000000000')) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid UUID, null UUID given.', $inLabel);
                }

                $dash = $this->fieldOptions['dash'] ?? true;
                if (!$dash ? !preg_match('~^[a-f0-9]{32}$~', (string) $in)
                           : !preg_match('~^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~', (string) $in)
                ) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid UUID.', $inLabel);
                }

                return true;
            }
            case Validation::TYPE_JSON: {
                // Validates JSON array/object inputs only.
                if ($spec) {
                    $chars = ($in[0] ?? '') . ($in[-1] ?? '');
                    if ($spec === 'array' && $chars !== '[]') {
                        return $this->toError(ValidationError::NOT_VALID,
                            '%s value is not a valid JSON array.', $inLabel);
                    } elseif ($spec === 'object' && $chars !== '{}') {
                        return $this->toError(ValidationError::NOT_VALID,
                            '%s value is not a valid JSON object.', $inLabel);
                    }
                }

                return true;
            }
        }

        // None but never, normally.
        throw new ValidationException('Unknown type %s', $type);
    }

    /**
     * Fill error property with given code and message/message params.
     *
     * @param  int               $code
     * @param  string            $message
     * @param  string|array|null $messageParams
     * @return bool
     * @internal
     */
    private function toError(int $code, string $message, string|array $messageParams = null): bool
    {
        $messageParams && $message = vsprintf($message, $messageParams);

        $this->error = ['code' => $code, 'message' => $message];

        return false;
    }
}
