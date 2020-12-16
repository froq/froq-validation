<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\validation;

use froq\validation\{ValidationException, Rule, Rules};
use froq\common\traits\OptionTrait;

/**
 * Validation.
 * @package froq\validation
 * @object  froq\validation\Validation
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   1.0
 */
final class Validation
{
    /**
     * @see froq\common\traits\OptionTrait
     * @since 4.2
     */
    use OptionTrait;

    /**
     * Types.
     * @const string
     */
    public const TYPE_INT      = 'int',
                 TYPE_FLOAT    = 'float',
                 TYPE_NUMERIC  = 'numeric',
                 TYPE_STRING   = 'string',
                 TYPE_BOOL     = 'bool',
                 TYPE_ENUM     = 'enum',
                 TYPE_EMAIL    = 'email',
                 TYPE_DATE     = 'date',
                 TYPE_TIME     = 'time',
                 TYPE_DATETIME = 'datetime',
                 TYPE_UNIXTIME = 'unixtime',
                 TYPE_JSON     = 'json',
                 TYPE_URL      = 'url',
                 TYPE_UUID     = 'uuid';

    /** @var array<string, array> */
    private array $rules = [];

    /** @var array<string> */
    private array $errors;

    /**
     * @var array
     * @since 4.2
     */
    private static array $optionsDefault = [
        'exceptionMode'       => false,
        'dropUndefinedFields' => true,
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
     * This method can be used in `init()` method in current controller in order to set or reset the rules
     * after getting them from database, a config file etc.
     *
     * @param  array $rules
     * @return void
     */
    public function setRules(array $rules): void
    {
        foreach ($rules as $key => $rule) {
            // Nested (eg: [user => [image => [fields => [id => [type => string], url => [type => url], ..]]]]).
            if (isset($rule['fields'])) {
                if (empty($rule['fields'])) {
                    throw new ValidationException('Rule `fields` must be a non-empty array');
                } elseif (!is_array($rule['fields'])) {
                    throw new ValidationException('Rule `fields` must be an array, %s given', get_type($rule));
                }

                $this->rules[$key] = new Rules($rule['fields']);
            }
            // Simple (eg: [image => [id => [type => string], url => [type => url], ..]]).
            else {
                $this->rules[$key] = new Rule($key, $rule);
            }
        }
    }

    /**
     * Get rules.
     * @return array
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get errors property.
     *
     * @return array|null
     */
    public function errors(): array|null
    {
        return $this->errors ?? null;
    }

    /**
     * Validate sanitizing given data.
     *
     * @param  array      &$data                This will override modifiying input data.
     * @param  array|null &$errors              Shortcut for call getErrors().
     * @param  bool|null   $dropUndefinedFields This will drop undefined data keys.
     * @return bool
     */
    public function validate(array &$data, array &$errors = null, bool $dropUndefinedFields = null): bool
    {
        if (empty($this->rules)) {
            throw new ValidationException('No rules to validate');
        }

        // Get rules.
        $rules = $this->rules;
        $ruleKeys = array_keys($rules);

        // Drop undefined data keys.
        $dropUndefinedFields ??= $this->getOption('dropUndefinedFields');
        if ($dropUndefinedFields) {
            foreach ($data as $key => $value) {
                if (!in_array($key, $ruleKeys)) {
                    unset($data[$key]);
                }
            }
        }

        // Populate data with null.
        foreach ($ruleKeys as $ruleKey) {
            if (!array_key_exists($ruleKey, $data)) {
                $data[$ruleKey] = null;
            }
        }

        [$exceptionMode, $useFieldNameAsLabel]
            = $this->getOptions(['exceptionMode', 'useFieldNameAsLabel']);

        foreach ($rules as $key => $rule) {
            // Nested.
            if ($rule instanceof Rules) {
                foreach ((array) $rule as $rule) {
                    $field = $rule->getField();
                    $fieldValue = $data[$key][$field] ?? null;
                    $fieldLabel = $useFieldNameAsLabel ? $key . '.' . $field : null;

                    // Real check here sanitizing/overriding input data.
                    if (!$rule->okay($fieldValue, $fieldLabel)) {
                        $errors[$key . '.' . $field] = $rule->error();
                    }

                    // @override
                    $data[$key][$field] = $fieldValue;
                }
            }
            // Simple.
            elseif ($rule instanceof Rule) {
                $field      = $rule->getField();
                $fieldValue = $data[$field] ?? null;
                $fieldLabel = $useFieldNameAsLabel ? $field : null;

                // Real check here sanitizing/overriding input data.
                if (!$rule->okay($fieldValue, $fieldLabel)) {
                    $errors[$field] = $rule->error();
                }

                // @override
                $data[$field] = $fieldValue;
            }
        }

        if ($errors != null) {
            if ($exceptionMode) {
                throw new ValidationException('Validation failed, use errors() to see error details',
                    errors: $errors);
            }

            // Store.
            $this->errors = $errors;

            return false;
        }

        return true;
    }
}
