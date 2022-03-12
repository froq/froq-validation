<?php
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-validation
 */
declare(strict_types=1);

namespace froq\validation\validator;

use froq\validation\{Validation, ValidationError};
use froq\common\trait\OptionTrait;

/**
 * Validator.
 *
 * @package froq\validation\validator
 * @object  froq\validation\validator\Validator
 * @author  Kerem Güneş
 * @since   6.0
 */
abstract class Validator
{
    use OptionTrait;

    /** @var mixed */
    protected mixed $input;

    /** @var string */
    protected string $inputLabel;

    /** @var froq\validation\validator\ValidatorResult */
    protected readonly ValidatorResult $result;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options)
    {
        $this->setOptions($options);
    }

    /**
     * Set input.
     *
     * @param  mixed $input
     * @return self
     */
    public function setInput(mixed $input): self
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Get input.
     *
     * @return mixed
     */
    public function getInput(): mixed
    {
        return $this->input;
    }

    /**
     * Set input label.
     *
     * @param  string $inputLabel
     * @return self
     */
    public function setInputLabel(string $inputLabel): self
    {
        $this->inputLabel = $inputLabel;

        return $this;
    }

    /**
     * Get input label.
     *
     * @return string.
     */
    public function getInputLabel(): string
    {
        return $this->inputLabel;
    }

    /**
     * Prepare self input for validation creating self result property.
     *
     * Note: This method used in validate() methods and can decide return true/false
     * states of these methods, can signal for dropping input field by "drop" option
     * or set result error by "required" option.
     *
     * @return void
     */
    protected final function prepare(): void
    {
        $this->result = new ValidatorResult();

        [$apply, $drop] = $this->getOptions(['apply', 'drop']);

        // Apply callable.
        if ($apply !== null && $this->isCallable($apply)) {
            $this->input = $apply($this->input);
        }

        // Apply drop option.
        if ($drop !== null && (
                // If drop option is true-like.
                $this->isTrue($drop) ||
                // If drop option is 'empty' and input is empty.
                ($drop === 'empty' && !$this->input) ||
                // If drop option is '' and input is ''.
                ($drop === '' && $this->input === '') ||
                // If drop option is 'null' and input is null.
                ($drop === 'null' && $this->input === null) ||
                // If drop option is callable and returns true.
                ($this->isCallable($drop) && $drop($this->input))
        )) {
            $this->result->dropped = true;
            return;
        }

        // Assign null/default.
        if ($this->isBlank()) {
            if ($this->getOption('nullable')) {
                $this->input = null;
            }
            // Note: also default will be validated.
            if ($this->hasOption('default')) {
                $this->input = $this->getOption('default');
            }
        }

        // Check required state.
        if ($this->isBlank()) {
            $required = $this->getOption('required');

            if (!$required) {
                $this->result->returned = true;
            } elseif ($required) {
                $this->result->error = $this->error(
                    ValidationError::REQUIRED,
                    '%s is required, none given.',
                    $this->inputLabel
                );
                $this->result->returned = false;
            }
        }
    }

    /**
     * Check whether self input is "" or null.
     *
     * @param  mixed $option
     * @return bool
     */
    protected final function isBlank(): bool
    {
        return ($this->input === '' || $this->input === null);
    }

    /**
     * Check whether given option is true or true-like.
     *
     * @param  mixed $option
     * @return bool
     */
    protected final function isTrue(mixed $option): bool
    {
        return ($option && ($option === true || (is_scalar($option) && strval($option) === '1')));
    }

    /**
     * Check whether given option is callable.
     *
     * @param  mixed $option
     * @return bool
     */
    protected final function isCallable(mixed $option): bool
    {
        return ($option && $option !== 'null' && $option !== 'void' && is_callable($option));
    }

    /**
     * Check given type.
     *
     * @param  string $type
     * @return bool
     */
    protected final function isType(string $type): bool
    {
        return is_type_of($this->input, $type);
    }

    /**
     * Check given spec pattern.
     *
     * @param  string $spec
     * @return bool
     */
    protected final function isMatch(string $spec): bool
    {
        return preg_test($spec, $this->input);
    }

    /**
     * Make an error.
     *
     * @param  int       $code
     * @param  string    $message
     * @param  mixed  ...$messageParams
     * @return array
     */
    protected final function error(int $code, string $message, mixed ...$messageParams): array
    {
        if ($messageParams) {
            $message = format($message, ...$messageParams);
        }

        return ['code' => $code, 'message' => $message];
    }

    /**
     * Create a validator instance.
     *
     * @param  array $options
     * @return froq\validation\validator\Validator
     * @throws froq\validation\validator\ValidatorException
     */
    public static final function create(array $options): Validator
    {
        // Callback spec overrides all validators.
        if (value($options, 'specType') === 'callback'
            or value($options, 'spec') instanceof \Closure) {
            return new CallbackValidator($options);
        }

        return match ($options['type']) {
            Validation::TYPE_INT,
            Validation::TYPE_FLOAT,
            Validation::TYPE_NUMBER,
            Validation::TYPE_NUMERIC  => new NumberValidator($options),
            Validation::TYPE_STRING   => new StringValidator($options),
            Validation::TYPE_ENUM     => new EnumValidator($options),
            Validation::TYPE_DATE,
            Validation::TYPE_TIME,
            Validation::TYPE_DATETIME => new DateTimeValidator($options),
            Validation::TYPE_UNIXTIME => new UnixtimeValidator($options),
            Validation::TYPE_EMAIL    => new EmailValidator($options),
            Validation::TYPE_URL      => new UrlValidator($options),
            Validation::TYPE_UUID     => new UuidValidator($options),
            Validation::TYPE_JSON     => new JsonValidator($options),
            Validation::TYPE_BOOL     => new BoolValidator($options),
            Validation::TYPE_ARRAY    => new ArrayValidator($options),

            // Not implemented.
            default => throw new ValidatorException('Invalid type `%s`', $options['type'])
        };
    }

    /**
     * Validate self input.
     *
     * @return froq\validation\validator\ValidatorResult
     */
    abstract public function validate(): ValidatorResult;
}
