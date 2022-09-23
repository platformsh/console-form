<?php

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
     *
     * @var string
     */
    protected $name;

    /**
     * Whether to include this field as a command-line option.
     *
     * @var bool
     */
    protected $includeAsOption = true;

    /**
     * The command-line option name for the field.
     *
     * @see self::getAsOption()
     *
     * @var string
     */
    protected $optionName;

    /**
     * The shortcut option name for the field.
     *
     * @see self::getAsOption()
     *
     * @var string
     */
    protected $shortcut;

    /**
     * The description of the field, used in command-line help.
     *
     * @see self::getAsOption()
     *
     * @var string
     */
    protected $description = '';

    /**
     * The questioning or explanatory line of text in an interactive question.
     *
     * If this is null (the default), the description of the field will be used.
     * If this is set to an empty string, no line will be shown.
     *
     * @var string|null
     */
    protected $questionLine = null;

    /**
     * Values used for auto-completion.
     *
     * @var null|array
     */
    protected $autoCompleterValues;

    /**
     * Whether the field is required.
     *
     * @var bool
     */
    protected $required = true;

    /**
     * The field's default value, used if the user did not enter anything.
     *
     * @see self::getFinalValue()
     *
     * @var mixed|null
     */
    protected $default;

    /**
     * The field's original default value (unaffected by the defaultCallback) used internally.
     *
     * @internal
     *
     * @var mixed|null
     */
    protected $originalDefault;

    /**
     * The prompt.
     *
     * @var string
     */
    protected $prompt = '> ';

    /**
     * The required value marker.
     *
     * @var string
     */
    protected $requiredMarker = '<fg=red>*</> ';

    /**
     * A callback used to calculate a dynamic default.
     *
     * The callback accepts an array of values entered previously for other form
     * fields. It returns the new default.
     *
     * @var callable
     */
    protected $defaultCallback;

    /**
     * Validator callbacks.
     *
     * @see self::validate()
     *
     * @var callable[] $validators
     *   An array of callbacks accepting a single value (the normalized input
     *   from the user). Each callback should return either a string or Boolean
     *   false if there was a validation error. Anything else is treated as
     *   success.
     */
    protected $validators = [];

    /**
     * The number of attempts the user can make to answer a question.
     *
     * @see Question::setMaxAttempts()
     *
     * @var int
     */
    protected $maxAttempts = 5;

    /**
     * Normalizer callbacks.
     *
     * @var callable[]
     *   An array of callbacks. Each callback takes one argument (the user
     *   input) and returns it normalized.
     */
    protected $normalizers = [];

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
    protected $conditions = [];

    /**
     * Array keys, under which the value of this field should be returned.
     *
     * For example, keys of ['foo', 'bar'] would result in the form returning
     * this field's value under $values['foo']['bar'].
     *
     * @var string[]
     */
    protected $valueKeys = [];

    /**
     * Avoids asking the field as a question, if it already has a default or is not required.
     *
     * @var bool
     */
    protected $avoidQuestion;

    /**
     * Constructor.
     *
     * @param string $name
     *   The field name.
     * @param array $config
     *   Other field configuration. The keys are protected properties of this
     *   class.
     */
    public function __construct($name, array $config = [])
    {
        $this->name = $name;
        foreach ($config as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Set or modify a configuration value.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function set($key, $value)
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
                if (!property_exists($this, $key)) {
                    throw new \InvalidArgumentException("Unrecognized config key: $key");
                }
                $this->$key = $value;
        }

        return $this;
    }

    /**
     * Get keys under which this field's value will be returned.
     *
     * @return string[]
     */
    public function getValueKeys()
    {
        return $this->valueKeys;
    }

    /**
     * Get the field's conditions.
     *
     * @return array
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    /**
     * Test whether the user's input matches the provided condition value.
     *
     * @param mixed $userValue
     * @param mixed $condition
     *
     * @return bool
     */
    public function matchesCondition($userValue, $condition)
    {
        if (is_callable($condition)) {
            return $condition($userValue);
        }

        return $userValue === $condition;
    }

    /**
     * Normalize user input.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function normalize($value)
    {
        if ($value === null) {
            return $value;
        }
        foreach ($this->normalizers as $normalizer) {
            $value = $normalizer($value);
        }

        return $value;
    }

    /**
     * Validate the field.
     *
     * @throws InvalidValueException if the input was invalid.
     *
     * @param mixed $value
     *   The user-entered value.
     * @param bool $fromOption
     *   Whether the validation is from a command-line option. This changes the default error message.
     */
    public function validate($value, $fromOption = false)
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
    public function getOptionName()
    {
        return $this->optionName ?: preg_replace('/[^a-z0-9-]+/', '-', strtolower($this->name));
    }

    /**
     * Returns whether to include this field as a command-line option.
     *
     * @return bool
     */
    public function includeAsOption()
    {
        return $this->includeAsOption;
    }

    /**
     * Get the field as a Console input option.
     *
     * @return InputOption
     */
    public function getAsOption()
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
     * Get the description of the field, used in help and interactive questions.
     *
     * @return string
     */
    protected function getDescription()
    {
        return $this->description ?: $this->name;
    }

    /**
     * Get the header text for an interactive question.
     *
     * @param bool $includeDefault
     *
     * @return string
     */
    protected function getQuestionHeader($includeDefault = true)
    {
        $header = '';
        if ($this->isRequired()) {
            $header .= $this->requiredMarker;
        }
        $header .= '<fg=green>' . $this->name . '</>';
        if ($this->includeAsOption) {
            $header .= ' (--' . $this->getOptionName() . ')';
        }
        if ($this->questionLine === null && !empty($this->description)) {
            $header .= "\n" . $this->description;
        } elseif (!empty($this->questionLine)) {
            $header .= "\n" . $this->questionLine;
        }
        if ($includeDefault && $this->default !== null) {
            $header .= "\n" . 'Default: <question>' . $this->formatDefault($this->default) . '</question>';
        }

        return $header;
    }

    /**
     * Returns whether the field should be asked as a Console question.
     *
     * @return bool
     */
    public function shouldAskAsQuestion()
    {
        if ($this->avoidQuestion) {
            return $this->isRequired() && !$this->hasDefault();
        }
        return true;
    }

    /**
     * Get the field as a Console question.
     *
     * @return Question
     */
    public function getAsQuestion()
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
     * Get the text of the interactive question.
     *
     * @return string
     */
    protected function getQuestionText()
    {
        return $this->getQuestionHeader() . "\n" . $this->prompt;
    }

    /**
     * Get the default as a string.
     *
     * Borrowed from \Symfony\Component\Console\Descriptor::formatDefaultValue().
     *
     * @param mixed $default
     *
     * @return string
     */
    protected function formatDefault($default)
    {
        if (is_string($default)) {
            return $default;
        }

        return json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the Console option mode for the field.
     *
     * @return int
     */
    protected function getOptionMode()
    {
        return InputOption::VALUE_REQUIRED;
    }

    /**
     * Test whether a value is empty.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function isEmpty($value)
    {
        return empty($value) && (!is_string($value) || !strlen($value));
    }

    /**
     * Get the value the user entered for this field.
     *
     * @param InputInterface $input
     * @param bool $normalize
     *
     * @return mixed|null
     *   The raw value, or null if the user did not enter anything. The value
     *   will be normalized if $normalize is set.
     */
    public function getValueFromInput(InputInterface $input, $normalize = true)
    {
        $optionName = $this->getOptionName();
        if (!$input->hasOption($optionName)) {
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
     *
     * @param mixed $userValue
     *
     * @return mixed
     */
    public function getFinalValue($userValue)
    {
        return $this->isEmpty($userValue)
            ? $this->default : $this->normalize($userValue);
    }

    /**
     * Check whether the user must enter a value for this field.
     *
     * @return bool
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Check whether a default is set for the field.
     *
     * @return bool
     */
    public function hasDefault()
    {
        return isset($this->default);
    }

    /**
     * Get the name of the field.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * React to a change in user input.
     *
     * @param array $previousValues
     */
    public function onChange(array $previousValues)
    {
       if (isset($this->defaultCallback)) {
           $callback = $this->defaultCallback;
           $this->default = $callback($previousValues);
       }
    }
}
