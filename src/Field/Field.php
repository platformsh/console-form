<?php

declare(strict_types=1);

namespace Platformsh\ConsoleForm\Field;

use Platformsh\ConsoleForm\Exception\InvalidValueException;
use Platformsh\ConsoleForm\Exception\MissingValueException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

class Field
{
    /**
     * The human-readable name of the field.
     */
    protected string $name;

    /**
     * Whether to include this field as a command-line option.
     */
    protected bool $includeAsOption = true;

    /**
     * The command-line option name for the field.
     *
     * @see self::getAsOption()
     */
    protected string $optionName = '';

    /**
     * The shortcut option name for the field.
     *
     * @see self::getAsOption()
     */
    protected string $shortcut = '';

    /**
     * The description of the field, used in command-line help.
     *
     * @see self::getAsOption()
     */
    protected string $description = '';

    /**
     * The questioning or explanatory line of text in an interactive question.
     *
     * If this is null (the default), the description of the field will be used.
     * If this is set to an empty string, no line will be shown.
     */
    protected ?string $questionLine = null;

    /**
     * Values used for auto-completion.
     */
    protected ?array $autoCompleterValues = null;

    /**
     * Whether the field is required.
     */
    protected bool $required = true;

    /**
     * The field's default value, used if the user did not enter anything.
     *
     * @see self::getFinalValue()
     *
     * @var mixed|null
     */
    protected mixed $default = null;

    /**
     * The field's original default value (unaffected by the defaultCallback) used internally.
     *
     * @internal
     *
     * @var mixed|null
     */
    protected mixed $originalDefault = null;

    /**
     * The prompt.
     */
    protected string $prompt = '> ';

    /**
     * The required value marker.
     */
    protected string $requiredMarker = '<fg=red>*</> ';

    /**
     * A callback used to calculate a dynamic default.
     *
     * The callback accepts an array of values entered previously for other form
     * fields. It returns the new default.
     *
     * @var callable|null
     */
    protected $defaultCallback = null;

    /**
     * Validator callbacks.
     *
     * @see self::validate()
     *
     * @var callable[]
     *   An array of callbacks accepting a single value (the normalized input
     *   from the user). Each callback should return either a string or Boolean
     *   false if there was a validation error. Anything else is treated as
     *   success.
     */
    protected array $validators = [];

    /**
     * The number of attempts the user can make to answer a question.
     *
     * @see Question::setMaxAttempts()
     */
    protected int $maxAttempts = 5;

    /**
     * Normalizer callbacks.
     *
     * @var callable[]
     *   An array of callbacks. Each callback takes one argument (the user
     *   input) and returns it normalized.
     */
    protected array $normalizers = [];

    /**
     * The conditions under which the field will be displayed or used.
     *
     * @todo this should not be the field's responsibility
     *
     * @var array
     *   An array mapping field keys (referencing other fields in the form) to
     *   user input. If any conditions are defined, all of them must match the
     *   user's input via self::matchesCondition(), otherwise the field will not
     *   be displayed. If the condition is a callable, it will be called with
     *   one argument (the user input) and expected to return a boolean.
     */
    protected array $conditions = [];

    /**
     * Array keys, under which the value of this field should be returned.
     *
     * For example, keys of ['foo', 'bar'] would result in the form returning
     * this field's value under $values['foo']['bar'].
     *
     * @var string[]
     */
    protected array $valueKeys = [];

    /**
     * Avoids asking the field as a question, if it already has a default or is not required.
     */
    protected bool $avoidQuestion = false;

    /**
     * @param string $name
     *   The field name.
     * @param array $config
     *   Other field configuration. The keys are protected properties of this
     *   class.
     */
    public function __construct(string $name, array $config = [])
    {
        $this->name = $name;
        foreach ($config as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Set or modify a configuration value.
     */
    public function set(string $key, mixed $value): static
    {
        switch ($key) {
            case 'validator':
                $this->validators[] = $value;
                break;

            case 'normalizer':
                $this->normalizers[] = $value;
                break;

            case 'default':
                $this->default = $this->originalDefault = $value;
                break;

            default:
                if (! property_exists($this, $key)) {
                    throw new \InvalidArgumentException("Unrecognized config key: {$key}");
                }
                $this->{$key} = $value;
        }

        return $this;
    }

    /**
     * Get keys under which this field's value will be returned.
     *
     * @return string[]
     */
    public function getValueKeys(): array
    {
        return $this->valueKeys;
    }

    /**
     * Get the field's conditions.
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Test whether the user's input matches the provided condition value.
     */
    public function matchesCondition(mixed $userValue, mixed $condition): bool
    {
        if (is_callable($condition)) {
            return $condition($userValue);
        }

        return $userValue === $condition;
    }

    /**
     * Validate the field.
     *
     * @param mixed $value
     *   The user-entered value.
     * @param bool $fromOption
     *   Whether the validation is from a command-line option. This changes the default error message.
     *@throws InvalidValueException if the input was invalid.
     */
    public function validate(mixed $value, bool $fromOption = false): void
    {
        if ($value === null) {
            return;
        }

        $normalized = $this->normalize($value);
        foreach ($this->validators as $validator) {
            $result = call_user_func($validator, $normalized);
            if (is_string($result)) {
                throw new InvalidValueException($result, $this);
            } elseif ($result === false) {
                $defaultMessage = $fromOption
                  ? sprintf('Invalid value for --%s: %s', $this->getOptionName(), $value)
                  : sprintf("Invalid value for '%s': %s", $this->name, $value);
                throw new InvalidValueException($defaultMessage, $this);
            }
        }
    }

    /**
     * Get the name of the option for the command line.
     *
     * @return string
     *   The option name. Either this is set via a config key (optionName), or a
     *   sanitized version of the field name is used.
     */
    public function getOptionName(): string
    {
        return $this->optionName ?: preg_replace('/[^a-z0-9-]+/', '-', strtolower($this->name));
    }

    /**
     * Returns whether to include this field as a command-line option.
     */
    public function includeAsOption(): bool
    {
        return $this->includeAsOption;
    }

    /**
     * Get the field as a Console input option.
     */
    public function getAsOption(): InputOption
    {
        return new InputOption(
            $this->getOptionName(),
            $this->shortcut,
            $this->getOptionMode(),
            $this->getDescription(),
            $this->default
        );
    }

    /**
     * Returns whether the field should be asked as a Console question.
     */
    public function shouldAskAsQuestion(): bool
    {
        if ($this->avoidQuestion) {
            return $this->isRequired() && ! $this->hasDefault();
        }
        return true;
    }

    /**
     * Get the field as a Console question.
     */
    public function getAsQuestion(): Question
    {
        $question = new Question($this->getQuestionText(), $this->default);
        $question->setMaxAttempts($this->maxAttempts);
        $question->setValidator(function ($value) {
            if ($this->isEmpty($value) && $this->isRequired()) {
                throw new MissingValueException("'{$this->name}' is required", $this);
            }
            $this->validate($value);

            return $value;
        });
        $question->setAutocompleterValues($this->autoCompleterValues);

        return $question;
    }

    /**
     * Test whether a value is empty.
     */
    public function isEmpty(mixed $value): bool
    {
        return empty($value) && (! is_string($value) || ! strlen($value));
    }

    /**
     * Get the value the user entered for this field.
     *
     * @return mixed|null
     *   The raw value, or null if the user did not enter anything. The value
     *   will be normalized if $normalize is set.
     */
    public function getValueFromInput(InputInterface $input, bool $normalize = true): mixed
    {
        $optionName = $this->getOptionName();
        if (! $input->hasOption($optionName)) {
            return null;
        }
        $value = $input->getOption($optionName);
        if ($this->isEmpty($value)) {
            return null;
        }
        if ($input->getParameterOption('--' . $optionName) === false && $value === $this->originalDefault) {
            return null;
        }

        return $normalize ? $this->normalize($value) : $value;
    }

    /**
     * Get the default value of this field, or the normalized user's value.
     */
    public function getFinalValue(mixed $userValue): mixed
    {
        return $this->isEmpty($userValue)
            ? $this->default : $this->normalize($userValue);
    }

    /**
     * Check whether the user must enter a value for this field.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Check whether a default is set for the field.
     */
    public function hasDefault(): bool
    {
        return isset($this->default);
    }

    /**
     * Get the name of the field.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * React to a change in user input.
     */
    public function onChange(array $previousValues): void
    {
        if (isset($this->defaultCallback)) {
            $callback = $this->defaultCallback;
            $this->default = $callback($previousValues);
        }
    }

    /**
     * Normalize user input.
     */
    protected function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        foreach ($this->normalizers as $normalizer) {
            $value = $normalizer($value);
        }

        return $value;
    }

    /**
     * Get the description of the field, used in help and interactive questions.
     */
    protected function getDescription(): string
    {
        return $this->description ?: $this->name;
    }

    /**
     * Get the header text for an interactive question.
     */
    protected function getQuestionHeader(bool $includeDefault = true): string
    {
        $header = '';
        if ($this->isRequired()) {
            $header .= $this->requiredMarker;
        }
        $header .= '<fg=green>' . $this->name . '</>';
        if ($this->includeAsOption) {
            $header .= ' (--' . $this->getOptionName() . ')';
        }
        if ($this->questionLine === null && ! empty($this->description)) {
            $header .= "\n" . $this->description;
        } elseif (! empty($this->questionLine)) {
            $header .= "\n" . $this->questionLine;
        }
        if ($includeDefault && $this->default !== null) {
            $header .= "\n" . 'Default: <question>' . $this->formatDefault($this->default) . '</question>';
        }

        return $header;
    }

    /**
     * Get the text of the interactive question.
     */
    protected function getQuestionText(): string
    {
        return $this->getQuestionHeader() . "\n" . $this->prompt;
    }

    /**
     * Get the default as a string.
     *
     * Borrowed from \Symfony\Component\Console\Descriptor::formatDefaultValue().
     */
    protected function formatDefault(mixed $default): string
    {
        if (is_string($default)) {
            return $default;
        }

        return json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the Console option mode for the field.
     */
    protected function getOptionMode(): int
    {
        return InputOption::VALUE_REQUIRED;
    }
}
