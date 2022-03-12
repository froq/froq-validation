<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation;

use froq\validation\validator\{Validator, ValidatorResult};

/**
 * Rule.
 *
 * A rule class, accepts a field & field options and is able to validate given
 * field input by its options, filling `$error` property with last occured error.
 *
 * @package froq\validation
 * @object  froq\validation\Rule
 * @author  Kerem Güneş
 * @since   1.0
 */
final class Rule
{
    /** @const array */
    public const AVAILABLE_TYPES = [
        Validation::TYPE_INT,      Validation::TYPE_FLOAT,
        Validation::TYPE_NUMBER,   Validation::TYPE_NUMERIC,
        Validation::TYPE_STRING,   Validation::TYPE_ENUM,
        Validation::TYPE_EMAIL,    Validation::TYPE_DATE,
        Validation::TYPE_TIME,     Validation::TYPE_DATETIME,
        Validation::TYPE_UNIXTIME, Validation::TYPE_JSON,
        Validation::TYPE_URL,      Validation::TYPE_UUID,
        Validation::TYPE_BOOL,     Validation::TYPE_ARRAY,
    ];

    /** @const array */
    public const BOOLABLE_OPTIONS = [
        'required', 'unsigned', 'dashed', 'cased',
        'nullable', 'strict', 'drop',
    ];

    /** @var string */
    private string $field;

    /** @var array */
    private array $fieldOptions;

    /** @var ?array */
    private ?array $error = null;

    /**
     * Constructor.
     *
     * @param  string $field
     * @param  array  $fieldOptions
     * @throws froq\validation\ValidationException
     */
    public function __construct(string $field, array $fieldOptions)
    {
        $field        || throw new ValidationException('Field name must not be empty');
        $fieldOptions || throw new ValidationException('Field options must not be empty');

        [$type, $spec] = array_select($fieldOptions, ['type', 'spec']);

        if ($type) {
            if (!in_array($type, self::AVAILABLE_TYPES, true)) {
                throw new ValidationException(
                    'Field `type` is invalid (given type: %s, available types: %a)',
                    [$type, self::AVAILABLE_TYPES]
                );
            } elseif (!$spec && $type == Validation::TYPE_ENUM) {
                throw new ValidationException(
                    'Type `%s` require `spec` definition as array in options (field: %s)',
                    [$type, $field]
                );
            }

            // Set spec for date/time stuff if none given.
            $spec || $spec = match ($type) {
                Validation::TYPE_DATE     => 'Y-m-d',
                Validation::TYPE_TIME     => 'H:i:s',
                Validation::TYPE_DATETIME => 'Y-m-d H:i:s',
                default                   => $spec
            };
        }

        if ($spec) {
            $specType = get_type($spec);

            if ($type == Validation::TYPE_ENUM && !equal($specType, 'array')) {
                throw new ValidationException(
                    'Invalid spec given, only an array accepted for enum types (field: %s)',
                    $field
                );
            } elseif ($type == Validation::TYPE_JSON && !equal($spec, 'array', 'object')) {
                throw new ValidationException(
                    'Invalid spec given, only `array` and `object` accepted for json types (field: %s)',
                    $field
                );
            }

            // No is_callable() check!!
            if ($spec instanceof \Closure) {
                $specType = 'callback';
            } else {
                // Detect regexp spec.
                if ($specType == 'string' && $spec[0] == '~') {
                    $specType = 'regexp';
                } elseif ($spec instanceof \RegExp) {
                    $spec     = $spec->pattern;
                    $specType = 'regexp';
                }
            }

            $fieldOptions['spec']     = $spec;
            $fieldOptions['specType'] = $specType;
        }

        // Set other rules (eg: required).
        foreach ($fieldOptions as $key => $value) {
            if (is_int($key)) {
                // Drop used or invalid options.
                unset($fieldOptions[$key]);

                if (in_array($value, self::BOOLABLE_OPTIONS, true)) {
                    $fieldOptions[$value] = true;
                }
            }
        }

        $this->field        = $field;
        $this->fieldOptions = ['field' => $field] + $fieldOptions;
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
     * @return ?array
     */
    public function error(): ?array
    {
        return $this->error;
    }

    /**
     * Validate given input, sanitizing/modifying it with given type in field options.
     *
     * @param  mixed                 &$input
     * @param  string|null            $inputLabel
     * @param  array|null            &$data   @internal
     * @param  ValidatorResult|null  &$result @internal
     * @return bool
     * @throws froq\validation\ValidationException
     */
    public function okay(mixed &$input, string $inputLabel = null, array &$data = null, ValidatorResult &$result = null): bool
    {
        $validator = Validator::create($this->fieldOptions)
            ->setInput($input)
            ->setInputLabel(
                $this->fieldOptions['label']
                    ?? format('Field %q', $inLabel ?? $this->field)
            );

        $result = $validator->validate($data);
        if ($error = $result->error) {
            $this->error = $error;
        } else {
            $input = $validator->getInput();
        }

        return ($this->error == null);
    }
}
