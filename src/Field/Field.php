<?php

namespace Platformsh\ConsoleForm\Field;

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
    protected $description;

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
     * Normalizer callback.
     *
     * @var callable
     *   A callback which takes one argument (the user input) and returns it
     *   normalized.
     */
    protected $normalizer;

    /**
     * The conditions under which the field will be displayed or used.
     *
     * @todo this should not be the field's responsibility
     *
     * @setter self::setConditions()
     *
     * @var array
     *   An array mapping field keys (referencing other fields in the form) to
     *   user input. If any conditions are defined, all of them must match the
     *   user's input via self::matchesCondition(), otherwise the field will not
     *   be displayed.
     */
    protected $conditions = [];

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
            switch ($key) {
                case 'validator':
                    $this->validators[] = $value;
                    break;

                default:
                    if (!property_exists($this, $key)) {
                        throw new \InvalidArgumentException("Unrecognized config key: $key");
                    }
                    $this->$key = $value;
            }
        }
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
     *   The user-entered value.
     * @param mixed $condition
     *   The condition value, as provided in self::setConditions().
     *
     * @return bool
     */
    public function matchesCondition($userValue, $condition)
    {
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
        if ($this->normalizer === null || $value === null) {
            return $value;
        }

        return call_user_func($this->normalizer, $value);
    }

    /**
     * Validate the field.
     *
     * @param mixed $value
     *   The user-entered value.
     *
     * @return string[]
     *   An empty array indicates that validation passed.
     */
    public function validate($value)
    {
        $errors = [];
        if ($this->required && $this->isEmpty($value)) {
            return ["'{$this->name}' is required"];
        }

        $normalized = $this->normalize($value);
        foreach ($this->validators as $validator) {
            $result = call_user_func($validator, $normalized);
            if (is_string($result)) {
                $errors[] = $result;
            }
            elseif ($result === false) {
                $errors[] = "Invalid value for '{$this->name}': $value";
            }
        }

        return $errors;
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
        return $this->optionName ?: preg_replace('/[ _]/', '-', strtolower($this->name));
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
          $this->description ? $this->description : $this->name,
          is_string($this->default) ? $this->default : null
        );
    }

    /**
     * Get the field as a Console question.
     *
     * @return Question
     */
    public function getAsQuestion()
    {
        $question = new Question($this->getQuestionText(), $this->default);
        $question->setValidator(function ($value) {
            $result = $this->validate($value);
            if (!empty($result)) {
                throw new \RuntimeException(implode("\n", $result));
            }

            return $value;
        });
        $question->setMaxAttempts(5);

        return $question;
    }

    /**
     * Get the text of the interactive question.
     *
     * @return string
     */
    protected function getQuestionText()
    {
        $text = $this->name;
        if (is_string($this->default)) {
            $text .= ' <question>[' . $this->default . ']</question>';
        }
        $text .= ': ';

        return $text;
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
}
