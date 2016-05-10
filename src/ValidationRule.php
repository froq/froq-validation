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

use Froq\Util\Traits\GetterTrait as Getter;

/**
 * @package    Froq
 * @subpackage Froq\Validation
 * @object     Froq\Validation\ValidationRule
 * @author     Kerem Güneş <k-gun@mail.com>
 */
final class ValidationRule
{
    /**
     * Getter.
     * @object Froq\Util\Traits\Getter
     */
    use Getter;

    /**
     * Field name.
     * @var string
     */
    private $fieldName;

    /**
     * Field type.
     * @var string
     */
    private $fieldType;

    /**
     * Field options.
     * @var array
     */
    private $fieldOptions = [];

    /**
     * Default value.
     * @var any
     */
    private $fieldDefault;

    /**
     * Field encoding. (@todo Replace invalid characters.)
     * @var string.
     */
    private $fieldEncoding;

    /**
     * Required flag.
     * @var bool
     */
    private $isRequired = false;

    /**
     * Unsigned flag for numerics.
     * @var bool
     */
    private $isUnsigned = false;

    /**
     * Fixed flag (will truncate and suppress fail if input exceeds the limit).
     * @var bool
     */
    private $isFixed = false;

    /**
     * Specification.
     * @var any
     */
    private $spec;

    /**
     * Specification type shortcut.
     * @var string
     */
    private $specType;

    /**
     * Limit value(s).
     * @var int|array
     */
    private $limit;

    /**
     * Lower limit.
     * @var int
     */
    private $limitMin;

    /**
     * Upper limit.
     * @var int
     */
    private $limitMax;

    /**
     * Fail text.
     * @var string
     */
    private $fail;

    /**
     * Constructor.
     * @param string $fieldName
     * @param array  $fieldOptions
     */
    final public function __construct(string $fieldName, array $fieldOptions)
    {
        $this->fieldName = $fieldName;
        $this->fieldOptions = $fieldOptions;

        if (empty($this->fieldOptions)) {
            throw new ValidationException('Field options should not be empty.');
        }

        // set type first
        if (!isset($this->fieldOptions['type'])) {
            throw new ValidationException(
                "Field type is not set in validation rules (field name: {$this->fieldName}).");
        } elseif (!in_array($this->fieldOptions['type'], [
            Validation::TYPE_INT,
            Validation::TYPE_FLOAT,
            Validation::TYPE_NUMERIC,
            Validation::TYPE_STRING,
            Validation::TYPE_BOOL,
            Validation::TYPE_ENUM,
            Validation::TYPE_EMAIL,
            Validation::TYPE_DATE,
            Validation::TYPE_DATETIME,
        ])) {
            throw new ValidationException(
                "Field type is not valid (field type: {$this->fieldOptions['type']}).");
        }
        $this->fieldType = $fieldOptions['type'];

        // check/set required stuff
        switch ($this->fieldType) {
            case Validation::TYPE_STRING:
                if (isset($this->fieldOptions['encoding'])) {
                    if (!in_array($this->fieldOptions['encoding'], Validation::ENCODING)) {
                        throw new ValidationException(
                            "Unimplemented encoding given (encoding: {$this->fieldOptions['encoding']}).");
                    }
                    $this->fieldEncoding = $this->fieldOptions['encoding'];
                }
                break;
            case Validation::TYPE_ENUM:
            case Validation::TYPE_DATE:
            case Validation::TYPE_DATETIME:
                if (!isset($this->fieldOptions['spec'])) {
                    throw new ValidationException(
                        "Enum, date and datetime types requires 'spec' definition (field name: {$this->fieldName})");
                }
                break;
        }

        // set spec
        if (isset($this->fieldOptions['spec'])) {
            $this->spec = $this->fieldOptions['spec'];
            $this->specType = gettype($this->spec);

            if ($this->specType != 'array' && $this->fieldType == Validation::TYPE_ENUM) {
                throw new ValidationException("Wrong spec given (field: {$this->fieldName}).");
            }

            // detect regex spec
            if ($this->specType == 'string' && $this->spec[0] == '~') {
                $this->specType = 'regex';
            }
        }

        // set default
        if (array_key_exists('default', $this->fieldOptions)) {
            $this->fieldDefault = $this->fieldOptions['default'];
        }

        // set limit
        if (isset($this->fieldOptions['limit'])) {
            $this->limit = $this->fieldOptions['limit'];
            if (is_array($this->limit)) {
                @ list($limitMin, $limitMax) = $this->limit;
                if (isset($limitMin)) {
                    $this->limitMin = (float) $limitMin;
                }
                if (isset($limitMax)) {
                    $this->limitMax = (float) $limitMax;
                }
            }
        }

        // set other rules
        foreach ($this->fieldOptions as $key => $value) {
            if (is_int($key) && is_array($value)) {
                foreach ($value as $option) {
                    switch ($option) {
                        case 'required': $this->isRequired = true; break;
                        case 'unsigned': $this->isUnsigned = true; break;
                        case     'fixed': $this->isFixed   = true; break;
                        // default: ignore others for now..
                    }
                }
            }
        }

        // check fix limit
        if ($this->isFixed && $this->limit === null && $this->limitMax === null) {
            throw new ValidationException('Limit option is required if fixed option is set.');
        }
    }

    /**
     * Validate.
     * @param  any &$input
     * @return bool
     */
    final public function ok(&$input): bool
    {
        $input = trim((string) $input);

        // check required issue
        if ($input === '' && $this->isRequired) {
            $this->fail = 'Field is required.';
            return false;
        }

        // assing default to input but do not return
        // true to check also given default
        if ($input === '') {
            $input = $this->fieldDefault;
        }

        // skip if null given as default
        // that also checks given default
        if ($input === null && !$this->isRequired) {
            return true;
        }

        // valide by field type
        switch ($this->fieldType) {
            case Validation::TYPE_INT:
            case Validation::TYPE_FLOAT:
                if (!is_numeric($input)) {
                    $this->fail = "Field value must be {$this->fieldType}.";
                    return false;
                }

                // sanitize
                $input = ($this->fieldType == Validation::TYPE_INT)
                    ? (int) $input : (float) $input;

                // make unsigned
                if ($this->isUnsigned) {
                    $input = abs($input);
                }

                // check limit(s)
                if ($this->limit !== null) {
                    if (is_numeric($this->limit) && $input !== $this->limit) {
                        $this->fail = "Field value could be only {$this->limit}.";
                        return false;
                    }
                    if ($this->limitMin !== null && $input < $this->limitMin) {
                        $this->fail = "Field value could be minimum {$this->limitMin}.";
                        return false;
                    }
                    if ($this->limitMax !== null && $input > $this->limitMax) {
                        $this->fail = "Field value could be maximum {$this->limitMax}.";
                        return false;
                    }
                }
                break;
            case Validation::TYPE_NUMERIC:
                if (!is_numeric($input)) {
                    $this->fail = 'Field value must be numeric.';
                    return false;
                }
                // make unsigned
                if ($this->isUnsigned) {
                    $input = preg_replace('~^-+~', '', $input);
                }
                break;
            case Validation::TYPE_STRING:
                // check regex if provided
                if ($this->specType == 'regex' && !preg_match($this->spec, $input)) {
                    $this->fail = 'Field value didn not match with given pattern.';
                    return false;
                }

                // check limit(s)
                if ($this->limit !== null) {
                    $isLimitNumeric = is_numeric($this->limit);
                    // should truncate?
                    if ($this->isFixed) {
                        $input = mb_substr($input, 0,
                            intval($isLimitNumeric ? $this->limit : $this->limitMax));
                    }

                    $inputLen = strlen($input);
                    if ($isLimitNumeric && $inputLen !== $this->limit) {
                        $this->fail = "Field value length must be {$this->limit}.";
                        return false;
                    }
                    if ($this->limitMin !== null && $inputLen < $this->limitMin) {
                        $this->fail = "Field value minimum length could be {$this->limitMin}.";
                        return false;
                    }
                    if ($this->limitMax !== null && $inputLen > $this->limitMax) {
                        $this->fail = "Field value maximum length could be {$this->limitMax}.";
                        return false;
                    }
                }
                break;
            case Validation::TYPE_BOOL:
                // set default bool spec
                if ($this->specType != 'array') {
                    $this->spec = ['true', 'false'];
                }

                if (!in_array($input, $this->spec)) {
                    $this->fail = sprintf('Field value should be one of %s options.',
                        join(', ', $this->spec));
                    return false;
                }
                break;
            case Validation::TYPE_ENUM:
                // @todo Multi-arrays?
                if (!in_array($input, $this->spec)) {
                    $this->fail = sprintf('Field value should be one of %s options.',
                        join(', ', $this->spec));
                    return false;
                }
                break;
            case Validation::TYPE_EMAIL:
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    $this->fail = 'Field value must be a valid email address.';
                    return false;
                }
                break;
            case Validation::TYPE_DATE:
            case Validation::TYPE_DATETIME:
                if ($this->specType == 'regex' && !preg_match($this->spec, $input)) {
                    $this->fail = 'Field value did not match with given pattern.';
                    return false;
                }

                // simply date check
                try {
                    if ($input && date($this->spec, strtotime($input)) != $input) {
                        $this->fail = 'Field value is not valid date/datetime.';
                        return false;
                    }
                } catch (\Throwable $e){}
                break;
        }

        // seems nothing wrong
        return true;
    }

    /**
     * @return bool
     */
    final public function isRequired(): bool
    {
        return ($this->isRequired == true);
    }

    /**
     * @return bool
     */
    final public function isUnsigned(): bool
    {
        return ($this->isUnsigned == true);
    }

    /**
     * @return bool
     */
    final public function isFixed(): bool
    {
        return ($this->isFixed == true);
    }

    /**
     * @return bool
     */
    final public function isInt(): bool
    {
        return ($this->fieldType == Validation::TYPE_INT);
    }

    /**
     * @return bool
     */
    final public function isFloat(): bool
    {
        return ($this->fieldType == Validation::TYPE_FLOAT);
    }

    /**
     * @return bool
     */
    final public function isString(): bool
    {
        return ($this->fieldType == Validation::TYPE_STRING);
    }

    /**
     * @return bool
     */
    final public function isNumeric(): bool
    {
        return ($this->fieldType == Validation::TYPE_NUMERIC);
    }

    /**
     * @return bool
     */
    final public function isBool(): bool
    {
        return ($this->fieldType == Validation::TYPE_BOOL);
    }

    /**
     * @return bool
     */
    final public function isEnum(): bool
    {
        return ($this->fieldType == Validation::TYPE_ENUM);
    }

    /**
     * @return bool
     */
    final public function isEmail(): bool
    {
        return ($this->fieldType == Validation::TYPE_EMAIL);
    }

    /**
     * @return bool
     */
    final public function isDate(): bool
    {
        return ($this->fieldType == Validation::TYPE_DATE);
    }

    /**
     * @return bool
     */
    final public function isDateTime(): bool
    {
        return ($this->fieldType == Validation::TYPE_DATETIME);
    }
}
