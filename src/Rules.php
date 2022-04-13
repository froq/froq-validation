<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation;

/**
 * A rule set class, used by `Validation` class internally.
 *
 * @package froq\validation
 * @object  froq\validation\Rules
 * @author  Kerem Güneş
 * @since   4.3
 * @internal
 */
final class Rules extends \stdClass
{
    /**
     * Constructor.
     *
     * @param  array $rules
     * @throws froq\validation\ValidationException
     */
    public function __construct(array $rules)
    {
        foreach ($rules as $field => $fieldOptions) {
            is_string($field) || throw new ValidationException(
                'Field name must a string, %t given', $field
            );

            // Simply set rules with keys as property.
            $this->$field = new Rule($field, $fieldOptions);
        }
    }
}
