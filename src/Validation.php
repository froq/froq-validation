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

use froq\validation\Rule;

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
                 TYPE_URL      = 'url',
                 TYPE_UUID     = 'uuid',
                 TYPE_JSON     = 'json';

    /**
     * Rules.
     * @var array<string, array>
     */
    private array $rules = [];

    /**
     * Fails.
     * @var array<string>
     */
    private array $fails = [];

    /**
     * Constructor.
     * @param array|null $rules
     */
    public function __construct(array $rules = null)
    {
        $rules && $this->setRules($rules);
    }

    /**
     * Set rules.
     *
     * This method can be used in `init()` method in current controller in order to set or reset
     * the rules after getting them from DB etc.
     *
     * @param  array $rules
     * @return void
     */
    public function setRules(array $rules): void
    {
        foreach ($rules as $key => $fields) {
            foreach ($fields as $field => $fieldOptions) {
                // Nested (eg: [user => [image => [fields => [id => [type => string], url => [type => url], ...]]]]).
                if (isset($fieldOptions['fields'])) {
                    foreach ((array) $fieldOptions['fields'] as $fieldField => $fieldFieldOptions) {
                        $this->rules[$key][$field][$fieldField] = new Rule($fieldField, $fieldFieldOptions);
                    }
                } else {
                    // Regular (eg: [user => [id => [type => string], url => [type => url]]]).
                    $this->rules[$key][$field] = new Rule($field, $fieldOptions);
                }
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
     * Get fails.
     * @return array
     */
    public function getFails(): array
    {
        return $this->fails;
    }

    /**
     * Validate.
     * @param  string      $key
     * @param  array      &$data              This will override modifiying input data.
     * @param  array|null &$fails             Shortcut for call getFails().
     * @param  bool        $dropUndefinedKeys This will drop undefined data keys.
     * @return bool
     */
    public function validate(string $key, array &$data, array &$fails = null,
        bool $dropUndefinedKeys = true): bool
    {
        // No rule to validate.
        if (empty($this->rules[$key])) {
            return true;
        }

        // Get rules.
        $rules = $this->rules[$key];
        $ruleKeys = array_keys($rules);

        // Drop undefined data keys.
        if ($dropUndefinedKeys) {
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

        foreach ($rules as $name => $rule) {
            // Nested?
            if (is_array($rule)) {
                foreach ($rule as $rule) {
                    $field = $rule->getField();
                    $fieldValue = $data[$name][$field] ?? null;

                    // Real check here sanitizing/overriding input data.
                    if (!$rule->ok($fieldValue, $name)) {
                        $fails[$name .'.'. $field] = $rule->getFail();
                    }

                    // @override
                    $data[$name][$field] = $fieldValue;
                }
            } else {
                $field = $rule->getField();
                $fieldValue = $data[$field] ?? null;

                // Real check here sanitizing/overriding input data.
                if (!$rule->ok($fieldValue, $field)) {
                    $fails[$field] = $rule->getFail();
                }

                // @override
                $data[$field] = $fieldValue;
            }
        }

        if ($fails != null) {
            $this->fails = $fails;
            return false;
        }
        return true;
    }
}
