<?php
/**
 * Copyright (c) 2016 Kerem Güneş
 *     <k-gun@mail.com>
 *
 * GNU General Public License v3.0
 *     <http://www.gnu.org/licenses/gpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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
     * @param  array      &$data      This will overwrite sanitizing input data.
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
                foreach ($rule as $nRules) {
                    $fieldName = $nRules->getFieldName();
                    $fieldValue =@ (string) $data[$name][$fieldName];

                    // real check here sanitizing/overwriting input data
                    if (!$nRules->ok($fieldValue)) {
                        $fails[$name .'.'. $fieldName] = $nRules->getFail();
                    }

                    // overwrite
                    $data[$name][$fieldName] = $fieldValue;
                }
            } else {
                $fieldName = $rule->getFieldName();
                $fieldValue =@ (string) $data[$fieldName];

                // real check here sanitizing/overwriting input data
                if (!$rule->ok($fieldValue)) {
                    $fails[$fieldName] = $rule->getFail();
                }

                // overwrite
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
     * in order to set (overwrite) its values after getting from db etc.
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
                            "For array types, 'spec' field must be a non-empty array (field: {$fieldName}).");
                    }
                    foreach ((array) $fieldSpec as $fieldSpecFieldName => $fieldSpecFieldOptions) {
                        $this->rules[$key][$fieldName][$fieldSpecFieldName] =
                            new ValidationRule($fieldSpecFieldName, $fieldSpecFieldOptions);
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
