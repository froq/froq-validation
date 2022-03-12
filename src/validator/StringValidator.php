<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation\validator;

use froq\validation\ValidationError;

/**
 * String Validator.
 *
 * @package froq\validation\validator
 * @object  froq\validation\validator\StringValidator
 * @author  Kerem Güneş
 * @since   6.0
 */
class StringValidator extends Validator
{
    /**
     * @inheritDoc froq\validation\validator\Validator
     */
    public function validate(): ValidatorResult
    {
        $this->prepare();

        if ($this->result->isDropped()
            || $this->result->isReturned()) {
            return $this->result;
        }

        if (!$this->isType('string')) {
            $this->result->error = $this->error(
                ValidationError::TYPE,
                '%s value must be string, %t given.',
                $this->inputLabel, $this->input
            );

            return $this->result;
        }

        $equal = $this->getOption('equal');

        if ($equal !== null) {
            if ($this->input !== $equal) {
                $this->result->error = $this->error(
                    ValidationError::NOT_EQUAL,
                    '%s value must be equal to %s.',
                    $this->inputLabel, $equal
                );
            }
        } else {
            [$spec, $specType] = $this->getOptions(['spec', 'specType']);

            if ($spec && $specType == 'regexp') {
                if (!$this->isMatch($spec)) {
                    $this->result->error = $this->error(
                        ValidationError::NOT_MATCH,
                        '%s value did not match with given pattern.',
                        $this->inputLabel
                    );
                }
            } else {
                [$encoding, $fixlen, $minlen, $maxlen, $limit, $quot, $html] = $this->getOptions(
                    ['encoding', 'fixlen', 'minlen', 'maxlen', 'limit', 'quot', 'html']
                );

                $len = mb_strlen($this->input, $encoding);

                if ($fixlen && $len != $fixlen) {
                    $this->result->error = $this->error(
                        ValidationError::LENGTH,
                        '%s value length must be %s.',
                        $this->inputLabel, $fixlen
                    );

                    return $this->result;
                } elseif ($minlen && $len < $minlen) {
                    $this->result->error = $this->error(
                        ValidationError::MIN_LENGTH,
                        '%s value length must be minimum %s.',
                        $this->inputLabel, $minlen
                    );

                    return $this->result;
                } elseif ($maxlen && $len > $maxlen) {
                    $this->result->error = $this->error(
                        ValidationError::MAX_LENGTH,
                        '%s value length must be maximum %s.',
                        $this->inputLabel, $maxlen
                    );

                    return $this->result;
                }

                // Apply limit option.
                $limit && $this->input = mb_substr($this->input, 0, $limit, $encoding);

                // Apply quot option.
                $quot && $this->input = strtr($this->input, ["'" => '&#39;', '"' => '&#34;']);

                // Apply HTML remove/encode option.
                $html && $this->input = match ($html) {
                    'remove' => $this->removeHtml(),
                    'encode' => $this->encodeHtml(),
                    default  => $this->encodeHtml()
                };
            }
        }

        return $this->result;
    }

    /**
     * Remove HTML tags.
     */
    private function removeHtml(): string
    {
        return preg_replace('~<(\w[\w-]*)\b[^>]*/?>(?:.*?</\1>)?~isu', '', $this->input);
    }

    /**
     * Encode HTML tags & quotes.
     */
    private function encodeHtml(): string
    {
        return strtr($this->input, ["'" => '&#39;', '"' => '&#34;', '<' => '&lt;', '>' => '&gt;']);
    }
}
