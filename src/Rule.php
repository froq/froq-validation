<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
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
 * @author  Kerem Güneş
 * @since   1.0
 */
final class Rule
{
    /** @var string */
    private string $field;

    /** @var array */
    private array $fieldOptions;

    /** @var array<int, string> */
    private array $error;

    /** @var array */
    private static array $availableTypes = [
        Validation::TYPE_INT,      Validation::TYPE_FLOAT,    Validation::TYPE_NUMERIC,
        Validation::TYPE_STRING,   Validation::TYPE_BOOL,     Validation::TYPE_ENUM,
        Validation::TYPE_EMAIL,    Validation::TYPE_DATE,     Validation::TYPE_TIME,
        Validation::TYPE_DATETIME, Validation::TYPE_UNIXTIME, Validation::TYPE_JSON,
        Validation::TYPE_URL,      Validation::TYPE_UUID,     Validation::TYPE_ARRAY,
    ];

    /** @var array */
    private static array $specableTypes = [
        Validation::TYPE_ENUM, Validation::TYPE_DATE, Validation::TYPE_DATETIME
    ];

    /** @var array */
    private static array $boolables = [
        'required', 'unsigned', 'cropped', 'dropped', 'nulled', 'stripped', 'fixed', 'html',
    ];

    /**
     * Constructor.
     *
     * @param string $field
     * @param array  $fieldOptions
     */
    public function __construct(string $field, array $fieldOptions)
    {
        $field        || throw new ValidationException('Field name must not be empty');
        $fieldOptions || throw new ValidationException('Field options must not be empty');

        [$type, $spec] = array_select($fieldOptions, ['type', 'spec']);

        if ($type != null) {
            if (!in_array($type, self::$availableTypes)) {
                throw new ValidationException('Field `type` is not valid (field type: %s, available types: %s)',
                    [$type, join(', ', self::$availableTypes)]);
            } elseif ($spec == null && in_array($type, self::$specableTypes)) {
                throw new ValidationException('Types %s require `spec` definition in options (field: %s)',
                    [join(', ', self::$specableTypes), $field]);
            }
        }

        // Set spec type.
        if ($spec != null) {
            if ($type == Validation::TYPE_JSON && !equals($spec, 'array', 'object')) {
                throw new ValidationException('Invalid spec given, only `array` and `object` accepted for json'
                    . ' types (field: %s)', $field);
            } elseif ($spec instanceof Closure) {
                $fieldOptions['specType'] = 'callback';
            } else {
                $fieldOptions['specType'] = gettype($spec);

                if ($fieldOptions['specType'] != 'array' && $type == Validation::TYPE_ENUM) {
                    throw new ValidationException('Invalid spec given, only an array accepted for enum types'
                        . ' (field: %s)', $field);
                }

                // Detect regexp spec.
                if ($fieldOptions['specType'] == 'string' && $spec[0] == '~') {
                    $fieldOptions['specType'] = 'regexp';
                }
            }
        }

        // Set other rules (eg: [foo => [type => int, .., required, ..]]).
        foreach ($fieldOptions as $key => $value) {
            if (is_int($key)) {
                // Drop used and non-valid items.
                unset($fieldOptions[$key]);

                if (in_array($value, self::$boolables)) {
                    $fieldOptions[$value] = true;
                }
            }
        }

        // Check cropped & fixed stuff.
        if (isset($fieldOptions['cropped']) && !isset($fieldOptions['limit'])) {
            throw new ValidationException('Option `limit` must not be empty when option `cropped` given');
        }
        if (isset($fieldOptions['fixed']) && !isset($fieldOptions['fixval']) && !isset($fieldOptions['fixlen'])) {
            throw new ValidationException('Option `fixval` or `fixlen` must not be empty when option `fixed` given');
        }

        $this->field        = $field;
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
     * Validate given input, sanitizing/modifying it with given type in field options.
     *
     * @param  scalar      &$in
     * @param  string|null  $inLabel
     * @param  array|null   $_ins     @internal
     * @param  array|null  &$_dropped @internal
     * @return bool
     * @throws froq\validation\ValidationException
     */
    public function okay(&$in, string $inLabel = null, array $_ins = null, bool &$_dropped = null): bool
    {
        [$type, $label, $default, $spec, $specType, $drop, $crop, $limit, $cast,
         $required, $unsigned, $cropped, $dropped, $nulled, $apply] = array_select($this->fieldOptions,
            ['type', 'label', 'default', 'spec', 'specType', 'drop', 'crop', 'limit', 'cast',
             'required', 'unsigned', 'cropped', 'dropped', 'nulled', 'apply']);

        if ($apply && is_callable($apply)) {
            $in = $apply($in);
        }

        if (isset($in) && !is_scalar($in) && !is_array($in)) {
            throw new ValidationException('Only scalar and array types accepted for validation, %s given',
                get_type($in));
        }

        $in      = is_string($in) ? trim($in) : $in;
        $inLabel = trim($label ?? ($inLabel ? 'Field \'' . $inLabel . '\'' : 'Field'));

        // Callback spec overrides all rules.
        if ($specType == 'callback') {
            /** @var string|array */
            $error = null;

            if ($spec($in, $_ins, $error) === false) {
                $code    = ValidationError::CALLBACK;
                $message = sprintf('Callback returned false for %s field.', stracut($inLabel, 'Field ') ?: $inLabel);

                if (is_string($error)) {
                    $message = $error;
                } elseif (is_array($error)) {
                    isset($error['code'])    && $code    = $error['code'];
                    isset($error['message']) && $message = $error['message'];
                }

                $this->toError($code, $message);

                return false;
            }

            return true;
        }

        // Nullable inputs.
        if (!$in && ($nulled || $cast || $cast == 'null')) {
            $in = is_callable($cast) ? $cast($in) : null;
        }

        // Assing default but do not return true to validate also given default.
        if ($in === '' || $in === null) {
            $in = $default;
        }

        // Check required issue.
        if ($required && ($in === '' || $in === null)) {
            return $this->toError(ValidationError::REQUIRED, '%s is required, none given.', $inLabel);
        }

        // Re-set dropped state.
        $_dropped = false;
        if (!$in && ($drop || $dropped)) {
            $_dropped = ($drop == 'null'  && $in === null)
                     || ($drop == 'empty' || $drop == true || $dropped);

            // Not needed to go far.
            if ($_dropped) {
                return true;
            }
        }

        // Skip if null given as default that also checks given default.
        if (!$required && $in === null) {
            return true;
        }

        // Crop.
        if ($crop || $cropped) {
            $in = mb_substr((string) $in, 0, (int) ($crop ?? $limit), (
                $encoding = $this->fieldOptions['encoding'] ?? null
            ));
        }

        // Validate by type.
        switch ($type) {
            case Validation::TYPE_ENUM: {
                // Cast.
                $cast && settype($in, $cast);

                $strict = $this->fieldOptions['strict'] ?? true;
                if (!in_array($in, $spec, (bool) $strict)) {
                    return $this->toError(ValidationError::NOT_FOUND,
                        '%s value must be one of these options: %s (input: %s).', [$inLabel, join(', ', $spec), $in]);
                }

                return true;
            }
            case Validation::TYPE_INT:
            case Validation::TYPE_FLOAT:
            case Validation::TYPE_NUMERIC: {
                if (!is_numeric($in)) {
                    return $this->toError(ValidationError::TYPE,
                        '%s value must be type of %s, %s given (input: %s).', [$inLabel, $type, get_type($in), $in]);
                }

                // Cast int/float.
                if ($type == Validation::TYPE_INT) {
                    $in = intval($in);
                } elseif ($type == Validation::TYPE_FLOAT) {
                    $in = floatval($in);
                }

                // Make unsigned.
                $unsigned && $in = abs($in);

                [$fixed, $fixval, $range] = array_select($this->fieldOptions, ['fixed', 'fixval', 'range']);

                // Check limit(s).
                if ($fixed || $fixval) {
                    if (json_encode($in) <> json_encode($fixed ?? $fixval)) {
                        return $this->toError(ValidationError::NOT_EQUAL,
                            '%s value must be only %s (input: %s).', [$inLabel, $fixval, $in]);
                    }
                } elseif (isset($range)) {
                    @ [$min, $max] = (array) $range;
                    if (isset($min) && $in < $min) {
                        return $this->toError(ValidationError::MIN_VALUE,
                            '%s value must be minimum %s (input: %s).', [$inLabel, $min, $in]);
                    }
                    if (isset($max) && $in > $max) {
                        return $this->toError(ValidationError::MAX_VALUE,
                            '%s value must be maximum %s (input: %s).', [$inLabel, $max, $in]);
                    }
                }

                return true;
            }
            case Validation::TYPE_STRING: {
                if (!is_string($in)) {
                    return $this->toError(ValidationError::TYPE,
                        '%s value must be string, %s given (input: %s).', [$inLabel, get_type($in), $in]);
                }

                // Check regexp if provided.
                if ($specType == 'regexp' && !preg_match($spec, $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with pattern %s (input: %s).', [$inLabel, $spec, $in]);
                }

                [$fixed, $fixlen, $limits, $minlen, $maxlen, $stripped, $html] = array_select($this->fieldOptions,
                    ['fixed', 'fixlen', 'limits', 'minlen', 'maxlen', 'stripped', 'html']);

                $encoding ??= $this->fieldOptions['encoding'] ?? null;

                $stripped && $in = strip_tags($in);

                // Check limit(s).
                if ($fixed || $fixlen) {
                    if (($len = mb_strlen($in, $encoding)) <> ($fixed ?? $fixlen)) {
                        return $this->toError(ValidationError::LENGTH,
                            '%s value length must be %s, (length: %s).', [$inLabel, $fixlen, $len]);
                    }
                } elseif (isset($limits) || isset($minlen) || isset($maxlen)) {
                    @ [$min, $max] = (array) ($limits ?? [$minlen, $maxlen]);
                    if (isset($min) && ($len = mb_strlen($in, $encoding)) < $min) {
                        return $this->toError(ValidationError::MIN_LENGTH,
                            '%s value minimum length must be %s, (length: %s).', [$inLabel, $min, $len]);
                    }
                    if (isset($max) && ($len = mb_strlen($in, $encoding)) > $max) {
                        return $this->toError(ValidationError::MAX_LENGTH,
                            '%s value maximum length must be %s, (length: %s).', [$inLabel, $max, $len]);
                    }
                }

                // Encode quot & html stuff.
                $html && $in = str_replace(["'", '"', '<', '>'], ['&#39;', '&#34;', '&lt;', '&gt;'], $in);

                return true;
            }
            case Validation::TYPE_BOOL: {
                if (!is_bool($in)) {
                    return $this->toError(ValidationError::TYPE,
                        '%s value must be true or false, %s given (input: %s).', [$inLabel, get_type($in), $in]);
                }

                return true;
            }
            case Validation::TYPE_EMAIL: {
                if ($specType == 'regexp' && !preg_match($spec, (string) $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with pattern %s (input: %s).', [$inLabel, $spec, $in]);
                }
                if (!filter_var($in, FILTER_VALIDATE_EMAIL)) {
                    return $this->toError(ValidationError::EMAIL,
                        '%s value must be a valid email address (input: %s).', [$inLabel, $in]);
                }

                return true;
            }
            case Validation::TYPE_DATE:
            case Validation::TYPE_TIME:
            case Validation::TYPE_DATETIME: {
                if ($specType == 'regexp' && !preg_match($spec, (string) $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with pattern %s (input: %s).', [$inLabel, $spec, $in]);
                }

                $date = date_create_from_format((string) $spec, (string) $in);
                if (!$date || $date->format($spec) <> $in) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid date/time/datetime (input: %s).', [$inLabel, $in]);
                }

                return true;
            }
            case Validation::TYPE_UNIXTIME: {
                $sin = (string) $in;

                // Accept 0 times? @default=false
                $zero = $this->fieldOptions['zero'] ?? false;
                if ($zero && $sin === '0') {
                    $in = (int) $in; // Cast.

                    return true;
                }

                if (!ctype_digit($sin) || strlen($sin) <> strlen((string) time())) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid Unixtime (input: %s).', [$inLabel, $in]);
                }

                $in = (int) $in; // Cast.

                return true;
            }
            case Validation::TYPE_URL: {
                if ($specType == 'regexp' && !preg_match($spec, (string) $in)) {
                    return $this->toError(ValidationError::NOT_MATCH,
                        '%s value did not match with pattern %s (input: %s).', [$inLabel, $spec, $in]);
                }

                if ($specType == 'array') {
                    // Remove silly empty components (eg: path always comes even url is empty).
                    $url = array_filter((array) parse_url((string) $in), 'strlen');

                    $missingComponents = array_diff($spec, array_keys($url));
                    if ($missingComponents) {
                        return $this->toError(ValidationError::NOT_VALID,
                            '%s value is not a valid URL (input: %s, missing components: %s).',
                                [$inLabel, $in, join(', ', $missingComponents)]);
                    }
                } elseif (!filter_var($in, FILTER_VALIDATE_URL)) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid URL (input: %s).', [$inLabel, $in]);
                }

                return true;
            }
            case Validation::TYPE_UUID: {
                // Accept null UUIDs? @default=false
                $null = $this->fieldOptions['null'] ?? false;

                if (!$null && ($in === '00000000000000000000000000000000' ||
                               $in === '00000000-0000-0000-0000-000000000000')) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid UUID, null UUID given (input: %s).', [$inLabel, $in]);
                }

                // Accept non-dashed UUIDs or both? @default=both
                $dash    = $this->fieldOptions['dash'] ?? null;
                $pattern = match ($dash) {
                    default => '~^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}|[a-f0-9]{32}$~',
                    true    => '~^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~',
                    false   => '~^[a-f0-9]{32}$~',
                };

                if (!preg_match($pattern, (string) $in)) {
                    return $this->toError(ValidationError::NOT_VALID,
                        '%s value is not a valid UUID (input: %s).', [$inLabel, $in]);
                }

                return true;
            }
            case Validation::TYPE_JSON: {
                // Validates JSON array/object inputs only.
                if ($spec) {
                    $chars = ($in[0] ?? '') . ($in[-1] ?? '');
                    if ($spec == 'array' && $chars != '[]') {
                        return $this->toError(ValidationError::NOT_VALID,
                            '%s value is not a valid JSON array (input: %s).', [$inLabel, $in]);
                    } elseif ($spec == 'object' && $chars != '{}') {
                        return $this->toError(ValidationError::NOT_VALID,
                            '%s value is not a valid JSON object (input: %s).', [$inLabel, $in]);
                    }
                }

                return true;
            }
            case Validation::TYPE_ARRAY: {
                if (!is_array($in)) {
                    return $this->toError(ValidationError::TYPE,
                        '%s value must be array, %s given (input: %s).', [$inLabel, get_type($in), $in]);
                }

                return true;
            }
        }

        // None but never, normally.
        throw new ValidationException('Unknown type `%s`', $type);
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
        $messageParams && $message = vsprintf($message, (array) $messageParams);

        $this->error = ['code' => $code, 'message' => $message];

        return false;
    }
}
