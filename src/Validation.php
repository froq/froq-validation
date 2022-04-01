<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation;

use froq\common\trait\OptionTrait;

/**
 * Validation.
 *
 * @package froq\validation
 * @object  froq\validation\Validation
 * @author  Kerem Güneş
 * @since   1.0
 */
final class Validation
{
    use OptionTrait;

    /**
     * Types.
     * @const string
     */
    public const TYPE_INT      = 'int',      TYPE_FLOAT    = 'float',
                 TYPE_NUMBER   = 'number',   TYPE_NUMERIC  = 'numeric',
                 TYPE_STRING   = 'string',   TYPE_ENUM     = 'enum',
                 TYPE_EMAIL    = 'email',    TYPE_DATE     = 'date',
                 TYPE_TIME     = 'time',     TYPE_DATETIME = 'datetime',
                 TYPE_UNIXTIME = 'unixtime', TYPE_JSON     = 'json',
                 TYPE_URL      = 'url',      TYPE_UUID     = 'uuid',
                 TYPE_BOOL     = 'bool',     TYPE_ARRAY    = 'array';

    /** @var array */
    private array $rules;

    /** @var array */
    private array $errors;

    /** @var array */
    private static array $optionsDefault = [
        'throwErrors'         => false,
        'dropUnknownFields'   => true,
        'useFieldNameAsLabel' => true,
    ];

    /**
     * Constructor.
     *
     * @param array|null $rules
     * @param array|null $options
     */
    public function __construct(array $rules = null, array $options = null)
    {
        $rules && $this->setRules($rules);

        $this->setOptions($options, self::$optionsDefault);
    }

    /**
     * Set rules.
     *
     * This method can be used in `init()` method in current controller in order to set or
     * reset the rules after getting them from database, a config file etc.
     *
     * @param  array $rules
     * @return void
     * @throws froq\validation\ValidationException
     */
    public function setRules(array $rules): void
    {
        foreach ($rules as $key => $rule) {
            // Skip empty rules (why, must be well defined already?).
            if (empty($rule)) {
                continue;
            }

            // Nested (eg: [user => [image => [fields => [id => [type => string], url => [type => url], ..]]]]).
            if (isset($rule['fields'])) {
                if (empty($rule['fields'])) {
                    throw new ValidationException('Rule `fields` must be a non-empty array');
                } elseif (!is_array($rule['fields'])) {
                    throw new ValidationException('Rule `fields` must be an array, %t given', $rule);
                }

                $this->rules[$key] = new Rules($rule['fields']);
            }
            // Single (eg: [image => [id => [type => string], url => [type => url], ..]]).
            else {
                $this->rules[$key] = new Rule($key, $rule);
            }
        }
    }

    /**
     * Get rules.
     *
     * @return ?array
     */
    public function getRules(): ?array
    {
        return $this->rules ?? null;
    }

    /**
     * Get errors property.
     *
     * @return ?array
     */
    public function errors(): ?array
    {
        return $this->errors ?? null;
    }

    /**
     * Validate sanitizing given data.
     *
     * @param  array      &$data              This will overridden.
     * @param  array|null &$errors            Shortcut for call errors().
     * @param  bool|null   $dropUnknownFields This will drop undefined data fields if true.
     * @return bool
     * @throws froq\validation\{ValidationException|ValidationError}
     */
    public function validate(array &$data, array &$errors = null, bool $dropUnknownFields = null): bool
    {
        if (empty($this->rules)) {
            throw new ValidationException('No rules given to validate');
        }

        $rules    = $this->rules;
        $ruleKeys = array_keys($rules);

        // Drop unknown data fields.
        $dropUnknownFields ??= $this->options['dropUnknownFields'];
        if ($dropUnknownFields) {
            $data = array_include($data, $ruleKeys);
        }

        // Populate absent data fields with null.
        $data = array_default($data, $ruleKeys, null);

        $errors = null; // @clear

        $useFieldNameAsLabel = $this->options['useFieldNameAsLabel'];

        foreach ($rules as $key => $rule) {
            // Nested.
            if ($rule instanceof Rules) {
                foreach ((array) $rule as $rule) {
                    $field      = $rule->field();
                    $fieldValue = $data[$key][$field] ?? null;
                    $fieldLabel = $useFieldNameAsLabel ? $key .'.'. $field : null;

                    // Real check here sanitizing/overriding input data.
                    if (!$rule->okay($fieldValue, $fieldLabel, $data, $result)) {
                        $errors[$key .'.'. $field] = $rule->error();
                    }

                    // Drop if dropped state is true.
                    if ($result->isDropped()) {
                        unset($data[$field]);
                    } else {
                        $data[$key][$field] = $fieldValue; // @override
                    }
                }
            }
            // Single.
            elseif ($rule instanceof Rule) {
                $field      = $rule->field();
                $fieldValue = $data[$field] ?? null;
                $fieldLabel = $useFieldNameAsLabel ? $field : null;

                // Real check here sanitizing/overriding input data.
                if (!$rule->okay($fieldValue, $fieldLabel, $data, $result)) {
                    $errors[$field] = $rule->error();
                }

                // Drop if dropped state is true.
                if ($result->isDropped()) {
                    unset($data[$field]);
                } else {
                    $data[$field] = $fieldValue; // @override
                }
            }
        }

        if ($errors) {
            if ($this->options['throwErrors']) {
                throw new ValidationError(
                    'Validation failed, use errors() to see error details',
                    errors: $errors
                );
            }

            $this->errors = $errors;

            return false;
        }

        return true;
    }
}
