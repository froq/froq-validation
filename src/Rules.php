<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 <https://opensource.org/licenses/apache-2.0>
 */
declare(strict_types=1);

namespace froq\validation;

use froq\validation\{ValidationException, Rule};

/**
 * Rules.
 *
 * @package froq\validation
 * @object  froq\validation\Rules
 * @author  Kerem Güneş <k-gun@mail.com>
 * @since   4.3
 */
final class Rules
{
    /**
     * Constructor.
     * @param array<string, array> $rules
     */
    public function __construct(array $rules)
    {
        foreach ($rules as $field => $fieldOptions) {
            if (!is_string($field)) {
                throw new ValidationException('Field name must a string, "%s" given',
                    [gettype($field)]);
            }

            // Simply set rules with keys as property.
            $this->{$field} = new Rule($field, $fieldOptions);
        }
    }
}
