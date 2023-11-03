<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
namespace froq\validation;

use froq\validation\validator\{Validator, ValidatorResult};

/**
 * A rule class, accepts a field & field options and is able to validate given
 * field input by its options, filling `$error` property with last occured error.
 *
 * @package froq\validation
 * @class   froq\validation\Rule
 * @author  Kerem Güneş
 * @since   1.0
 */
class Rule
{
    /** Boolable options (given with no keys). */
    public const BOOLABLE_OPTIONS = ['required', 'unsigned', 'nullable', 'strict', 'drop'];

    /** Field name. */
    public readonly string $field;

    /** Field options. */
    public readonly array $fieldOptions;

    /** Error. */
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
        $field        || throw new ValidationException('Field cannot be empty');
        $fieldOptions || throw new ValidationException('Field options cannot be empty');

        [$type, $spec] = array_select($fieldOptions, ['type', 'spec']);

        if ($type) {
            if (!in_array($type, ValidationType::all(), true)) {
                throw new ValidationException(
                    'Option "type" is invalid (given type: %s, available types: %a)',
                    [$type, ValidationType::all()]
                );
            } elseif (!$spec && $type === ValidationType::ENUM) {
                throw new ValidationException(
                    'Option "type.%s" requires "spec" definition as array in options (field: %s)',
                    [$type, $field]
                );
            }

            // Set spec for date/time stuff if none given.
            $spec || $spec = match ($type) {
                ValidationType::DATE     => 'Y-m-d',
                ValidationType::TIME     => 'H:i:s',
                ValidationType::DATETIME => 'Y-m-d H:i:s',
                default                  => $spec
            };
        }

        if ($spec) {
            $specType = get_type($spec);

            if ($type === ValidationType::ENUM && !equal($specType, 'array')) {
                throw new ValidationException(
                    'Invalid "spec" given, only an array accepted for enum types (field: %s)',
                    $field
                );
            } elseif ($type === ValidationType::JSON && !equal($spec, 'array', 'object')) {
                throw new ValidationException(
                    'Invalid "spec" given, only array and object accepted for json types (field: %s)',
                    $field
                );
            }

            // No is_callable() check!!
            if ($spec instanceof \Closure) {
                $specType = 'callback';
            } else {
                // Detect regexp spec.
                if ($specType === 'string' && $spec[0] === '~') {
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
        $this->fieldOptions = ['name' => $field] + $fieldOptions;
    }

    /**
     * Get error property.
     *
     * @return array|null
     */
    public function error(): array|null
    {
        return $this->error;
    }

    /**
     * Validate given input, sanitizing/modifying it with given type in field options.
     *
     * @param  mixed                &$input
     * @param  string|null           $inputLabel
     * @param  array|null           &$data   @internal
     * @param  ValidatorResult|null &$result @internal
     * @return bool
     */
    public function okay(mixed &$input, string $inputLabel = null, array &$data = null, ValidatorResult &$result = null): bool
    {
        $validator = Validator::create($this->fieldOptions)
            ->setInput($input)
            ->setInputLabel(
                $this->fieldOptions['label']
                    ?? format('Field %q', $inputLabel ?? $this->field)
            );

        // Reset (in case).
        $this->error = null;

        $result = $validator->validate($data);
        if ($error = $result->error) {
            $this->error = $error;
        } else {
            $input = $validator->getInput();
        }

        return ($this->error === null);
    }
}
