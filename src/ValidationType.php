<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation;

/**
 * Validation type registry.
 *
 * @package froq\validation
 * @object  froq\validation\ValidationType
 * @author  Kerem Güneş
 * @since   6.0
 */
final class ValidationType
{
    /**
     * Types.
     * @const string
     */
    public const INT      = 'int',      FLOAT    = 'float',
                 NUMBER   = 'number',   NUMERIC  = 'numeric',
                 STRING   = 'string',   ENUM     = 'enum',
                 EMAIL    = 'email',    DATE     = 'date',
                 TIME     = 'time',     DATETIME = 'datetime',
                 UNIXTIME = 'unixtime', JSON     = 'json',
                 URL      = 'url',      UUID     = 'uuid',
                 BOOL     = 'bool',     ARRAY    = 'array';

    /**
     * Get all types.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        static $all; // For speed (diff: mem=~1Kb, cpu=~10x).
        return $all ??= get_class_constants(self::class, false, false);
    }
}
