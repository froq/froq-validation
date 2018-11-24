<?php
/**
 * Copyright (c) 2015 Kerem Güneş
 *
 * MIT License <https://opensource.org/licenses/mit>
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
 * @object     Froq\Validation\ValidationRule
 * @author     Kerem Güneş <k-gun@mail.com>
 */
final class ValidationRule
{
    /**
     * Field name.
     * @var string
     */
    private $fieldName;

    /**
     * Field label.
     * @var string
     */
    private $fieldLabel;

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
     * Is unsigned.
     * @var bool
     */
    private $isUnsigned = false;

    /**
     * Is fixed (will truncate and suppress fail if input exceeds the limit).
     * @var bool
     */
    private $isFixed = false;

    /**
     * Spec.
     * @var any
     */
    private $spec;

    /**
     * Spec type.
     * @var string
     */
    private $specType;

    /**
     * Limit.
     * @var int|float|array
     */
    private $limit;

    /**
     * Limit min.
     * @var float
     */
    private $limitMin;

    /**
     * Limit max.
     * @var float
     */
    private $limitMax;

    /**
     * Fail.
     * @var string
     */
    private $fail;

    /**
     * Constructor.
     * @param string $fieldName
     * @param array  $fieldOptions
     */
    public function __construct(string $fieldName, array $fieldOptions)
    {
        if (empty($fieldOptions)) {
            throw new ValidationException('Field options must not be empty.');
        }

        $this->fieldName = $fieldName;
        $this->fieldLabel = $fieldOptions['label'] ?? null;
        $this->fieldOptions = $fieldOptions;

        // set type first
        if (!isset($this->fieldOptions['type'])) {
            throw new ValidationException(
                "Field type is not set in validation rules (field: {$this->fieldName}).");
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
            Validation::TYPE_URL,
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
                        "Enum, date and datetime types requires 'spec' definition (field: {$this->fieldName})");
                }
                break;
        }

        // set spec
        if (isset($this->fieldOptions['spec'])) {
            $this->spec = $this->fieldOptions['spec'];
            $this->specType = gettype($this->spec);

            if ($this->specType != 'array' && $this->fieldType == Validation::TYPE_ENUM) {
                throw new ValidationException("Wrong spec given, only an array accepted (field: {$this->fieldName}).");
            }

            // detect regexp spec
            if ($this->specType == 'string' && $this->spec[0] == '~') {
                $this->specType = 'regexp';
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
                @ [$limitMin, $limitMax] = $this->limit;
                if (isset($limitMin)) {
                    $this->limitMin = (float) $limitMin;
                }
                if (isset($limitMax)) {
                    $this->limitMax = (float) $limitMax;
                }
            }
        }

        // set other rules (eg: [foo => [type => int, ... [required, ...]]])
        foreach ($this->fieldOptions as $key => $value) {
            if (is_int($key) && is_array($value)) {
                foreach ($value as $option) {
                    switch ($option) {
                        case 'required': $this->isRequired = true; break;
                        case 'unsigned': $this->isUnsigned = true; break;
                        case    'fixed': $this->isFixed    = true; break;
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
    public function ok(&$input): bool
    {
        $input = trim((string) $input);
        $inputLabel = trim($this->fieldLabel ?: 'Field');

        // check required issue
        if ($input === '' && $this->isRequired) {
            $this->fail = sprintf('%s is required.', $inputLabel);
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
                    $this->fail = sprintf('%s value must be type of %s.', $inputLabel, $this->fieldType);
                    return false;
                }

                // sanitize
                $input = ($this->fieldType == Validation::TYPE_INT) ? intval($input) : floatval($input);

                // make unsigned
                if ($this->isUnsigned) {
                    $input = abs($input);
                }

                // check limit(s)
                if ($this->limit !== null) {
                    if (is_numeric($this->limit) && strval($input) !== strval($this->limit)) {
                        $this->fail = sprintf('%s value could be only %s.', $inputLabel, $this->limit);
                        return false;
                    }
                    if ($this->limitMin !== null && $input < $this->limitMin) {
                        $this->fail = sprintf('%s value could be minimum %s.', $inputLabel, $this->limitMin);
                        return false;
                    }
                    if ($this->limitMax !== null && $input > $this->limitMax) {
                        $this->fail = sprintf('%s value could be maximum %s.', $inputLabel, $this->limitMax);
                        return false;
                    }
                }
                break;
            case Validation::TYPE_NUMERIC:
                if (!is_numeric($input)) {
                    $this->fail = sprintf('%s value must be numeric.', $inputLabel);
                    return false;
                }
                // make unsigned
                if ($this->isUnsigned) {
                    $input = abs($input);
                }
                break;
            case Validation::TYPE_STRING:
                // check regexp if provided
                if ($this->specType == 'regexp' && !preg_match($this->spec, $input)) {
                    $this->fail = sprintf('%s value did not match with given pattern.', $inputLabel);
                    return false;
                }

                // check limit(s)
                if ($this->limit !== null) {
                    $isLimitNumeric = is_numeric($this->limit);
                    // should truncate?
                    if ($this->isFixed) {
                        $input = mb_substr($input, 0, intval($isLimitNumeric ? $this->limit : $this->limitMax));
                    }

                    $inputLen = strlen($input);
                    if ($isLimitNumeric && $inputLen !== $this->limit) {
                        $this->fail = sprintf('%s value length must be %s.', $inputLabel, $this->limit);
                        return false;
                    }
                    if ($this->limitMin !== null && $inputLen < $this->limitMin) {
                        $this->fail = sprintf('%s value minimum length could be %s.', $inputLabel, $this->limitMin);
                        return false;
                    }
                    if ($this->limitMax !== null && $inputLen > $this->limitMax) {
                        $this->fail = sprintf('%s value maximum length could be %s.', $inputLabel, $this->limitMax);
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
                    $this->fail = sprintf('%s value could be one of %s options.', $inputLabel, join(', ', $this->spec));
                    return false;
                }
                break;
            case Validation::TYPE_ENUM:
                // @todo Multi-arrays?
                if (!in_array($input, $this->spec)) {
                    $this->fail = sprintf('%s value could be one of %s options.', $inputLabel, join(', ', $this->spec));
                    return false;
                }
                break;
            case Validation::TYPE_EMAIL:
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    $this->fail = sprintf('%s value must be a valid email address.', $inputLabel);
                    return false;
                }
                break;
            case Validation::TYPE_DATE:
            case Validation::TYPE_DATETIME:
                if ($this->specType == 'regexp' && !preg_match($this->spec, $input)) {
                    $this->fail = sprintf('%s value did not match with given pattern.', $inputLabel);
                    return false;
                }

                if ($input != date($this->spec, strtotime($input))) {
                    $this->fail = sprintf('%s value is not valid date/datetime.', $inputLabel);
                    return false;
                }
                break;
            case Validation::TYPE_URL:
                if ($this->specType == 'regexp' && !preg_match($this->spec, $input)) {
                    $this->fail = sprintf('%s value did not match with given pattern.', $inputLabel);
                    return false;
                }
                if ($this->specType == 'array') {
                    // remove silly empty components (eg: path always comes even url is empty)
                    $url = array_filter((array) parse_url($input), 'strlen');
                    $components = [];
                    foreach ($this->spec as $component) {
                        if (!isset($url[$component])) {
                            $components[] = $component;
                        }
                    }
                    if (!empty($components)) {
                        $this->fail = sprintf('%s value is not a valid URL (missing components: %s).',
                            $inputLabel, join(', ', $components));
                        return false;
                    }
                    return true;
                }
                if (!filter_var($input, FILTER_VALIDATE_URL)) {
                    $this->fail = sprintf('%s value is not a valid URL.', $inputLabel);
                    return false;
                }
                break;
        }

        // seems nothing wrong
        return true;
    }

    /**
     * Get field name.
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Get field label.
     * @return ?string
     */
    public function getFieldLabel(): ?string
    {
        return $this->fieldLabel;
    }

    /**
     * Get field type.
     * @return string
     */
    public function getFieldType(): string
    {
        return $this->fieldType;
    }

    /**
     * Get options.
     * @return array
     */
    public function getFieldOptions(): array
    {
        return $this->fieldOptions;
    }

    /**
     * Get field default.
     * @return any
     */
    public function getFieldDefault()
    {
        return $this->fieldDefault;
    }

    /**
     * Get field encoding.
     * @return ?string
     */
    public function getFieldEncoding(): ?string
    {
        return $this->fieldEncoding;
    }

    /**
     * Get spec.
     * @return any
     */
    public function getSpec()
    {
        return $this->spec;
    }

    /**
     * Get spec type.
     * @return ?string
     */
    public function getSpecType(): ?string
    {
        return $this->specType;
    }

    /**
     * Get limit.
     * @return any
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * Get limit min.
     * @return ?float
     */
    public function getLimitMin(): ?float
    {
        return $this->limitMin;
    }

    /**
     * Get limit max.
     * @return ?float
     */
    public function getLimitMax(): ?float
    {
        return $this->limitMax;
    }

    /**
     * Get fail.
     * @return ?string
     */
    public function getFail(): ?string
    {
        return $this->fail;
    }

    /**
     * Is required.
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->isRequired == true;
    }

    /**
     * Is unsigned.
     * @return bool
     */
    public function isUnsigned(): bool
    {
        return $this->isUnsigned == true;
    }

    /**
     * Is fixed.
     * @return bool
     */
    public function isFixed(): bool
    {
        return $this->isFixed == true;
    }

    /**
     * Is int.
     * @return bool
     */
    public function isInt(): bool
    {
        return $this->fieldType == Validation::TYPE_INT;
    }

    /**
     * Is float.
     * @return bool
     */
    public function isFloat(): bool
    {
        return $this->fieldType == Validation::TYPE_FLOAT;
    }

    /**
     * Is string.
     * @return bool
     */
    public function isString(): bool
    {
        return $this->fieldType == Validation::TYPE_STRING;
    }

    /**
     * Is numeric.
     * @return bool
     */
    public function isNumeric(): bool
    {
        return $this->fieldType == Validation::TYPE_NUMERIC;
    }

    /**
     * Is bool.
     * @return bool
     */
    public function isBool(): bool
    {
        return $this->fieldType == Validation::TYPE_BOOL;
    }

    /**
     * Is enum.
     * @return bool
     */
    public function isEnum(): bool
    {
        return $this->fieldType == Validation::TYPE_ENUM;
    }

    /**
     * Is email.
     * @return bool
     */
    public function isEmail(): bool
    {
        return $this->fieldType == Validation::TYPE_EMAIL;
    }

    /**
     * Is date.
     * @return bool
     */
    public function isDate(): bool
    {
        return $this->fieldType == Validation::TYPE_DATE;
    }

    /**
     * Is datetime.
     * @return bool
     */
    public function isDateTime(): bool
    {
        return $this->fieldType == Validation::TYPE_DATETIME;
    }
}
