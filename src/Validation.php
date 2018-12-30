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

namespace Froq\Validation;

/**
 * @package    Froq
 * @subpackage Froq\Validation
 * @object     Froq\Validation\Validation
 * @author     Kerem Güneş <k-gun@mail.com>
 */
final class Validation
{
    /**
     * Types.
     * @const string
     */
    public const TYPE_INT        = 'int',
                 TYPE_FLOAT      = 'float',
                 TYPE_NUMERIC    = 'numeric',
                 TYPE_STRING     = 'string',
                 TYPE_BOOL       = 'bool',
                 TYPE_ENUM       = 'enum',
                 TYPE_EMAIL      = 'email',
                 TYPE_DATE       = 'date',
                 TYPE_DATETIME   = 'datetime',
                 TYPE_ARRAY      = 'array',
                 TYPE_URL        = 'url';

    // @todo Replace invalid characters.
    public const ENCODING        = ['ascii', 'unicode'];

    /**
     * Rules.
     * @var array
     */
    private $rules = [];

    /**
     * Fails.
     * @var array
     */
    private $fails = [];

    /**
     * Constructor.
     * @param array|null $rules
     */
    public function __construct(array $rules = null)
    {
        if ($rules != null) {
            $this->setRules($rules);
        }
    }

    /**
     * Validate.
     * @param  string     $key
     * @param  array      &$data      This will override sanitizing input data.
     * @param  array|null &$fails     Shortcut instead of to call self::getFails().
     * @param  bool       $dropUndefs This will drop undefined data keys
     * @return bool
     */
    public function validate(string $key, array &$data, array &$fails = null, bool $dropUndefs = true): bool
    {
        // no rule to validate
        if (!isset($this->rules[$key])) {
            return true;
        }

        // get rules
        $rules = $this->rules[$key];
        $ruleKeys = array_keys($rules);

        // drop undefined data keys
        if ($dropUndefs) {
            foreach ($data as $key => $value) {
                if (!in_array($key, $ruleKeys)) {
                    unset($data[$key]);
                }
            }
        }

        // populate data with null
        foreach ($ruleKeys as $ruleKey) {
            if (!array_key_exists($ruleKey, $data)) {
                $data[$ruleKey] = null;
            }
        }

        foreach ($rules as $name => $rule) {
            // nested?
            if (is_array($rule)) {
                foreach ($rule as $nRule) {
                    $fieldName = $nRule->getFieldName();
                    $fieldValue =@ (string) $data[$name][$fieldName];

                    // real check here sanitizing/overwriting input data
                    if (!$nRule->ok($fieldValue)) {
                        $fails[$name .'.'. $fieldName] = $nRule->getFail();
                    }

                    // override
                    $data[$name][$fieldName] = $fieldValue;
                }
            } else {
                $fieldName = $rule->getFieldName();
                $fieldValue =@ (string) $data[$fieldName];

                // real check here sanitizing/overwriting input data
                if (!$rule->ok($fieldValue)) {
                    $fails[$fieldName] = $rule->getFail();
                }

                // override
                $data[$fieldName] = $fieldValue;
            }
        }

        // store for later
        $this->setFails($fails);

        return empty($fails);
    }

    /**
     * Set rules.
     * @note    This method could be used in "service::init" method
     * in order to set (override) its values after getting from db etc.
     * @param  array $rules
     * @return self
     */
    public function setRules(array $rules): self
    {
        foreach ($rules as $key => $fields) {
            foreach ($fields as $fieldName => $fieldOptions) {
                // nested?
                $fieldType =@ $fieldOptions['type'];
                if ($fieldType == self::TYPE_ARRAY) {
                    $fieldSpec =@ $fieldOptions['spec'];
                    if (empty($fieldSpec)) {
                        throw new ValidationException(
                            "For array types, 'spec' field must be a non-empty array (field: {$fieldName})");
                    }
                    foreach ((array) $fieldSpec as $fieldSpecFieldName => $fieldSpecFieldOptions) {
                        $this->rules[$key][$fieldName][$fieldSpecFieldName] = new ValidationRule(
                            $fieldSpecFieldName, $fieldSpecFieldOptions
                        );
                    }
                } else {
                    $this->rules[$key][$fieldName] = new ValidationRule($fieldName, $fieldOptions);
                }
            }
        }

        return $this;
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
     * Set fails.
     * @param  array $fails
     * @return self
     */
    public function setFails(array $fails = null): self
    {
        if (!empty($fails)) {
            foreach ($fails as $fieldName => $fail) {
                $this->fails[$fieldName] = $fail;
            }
        }

        return $this;
    }

    /**
     * Get fails.
     * @return array
     */
    public function getFails(): array
    {
        return $this->fails;
    }
}
