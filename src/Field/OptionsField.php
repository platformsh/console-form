<?php

declare(strict_types=1);

namespace Platformsh\ConsoleForm\Field;

use Platformsh\ConsoleForm\Exception\MissingValueException;
use Symfony\Component\Console\Question\ChoiceQuestion;

class OptionsField extends Field
{
    protected array $options = [];

    protected bool $asChoice = true;

    protected bool $allowOther = false;

    protected bool $autoDescribe = true;

    protected bool $chooseWithNumber = false;

    /**
     * A callback used to calculate dynamic options.
     *
     * The callback accepts an array of values entered previously for other form
     * fields. It returns the new options, as an array.
     *
     * @var callable
     */
    protected $optionsCallback;

    public function __construct($name, array $config = [])
    {
        parent::__construct($name, $config);
        $this->validators[] = function ($value) {
            if ($this->allowOther) {
                return true;
            }
            $options = $this->validOptions();

            return in_array($value, $options, true)
                ? true : "{$value} is not one of: " . implode(', ', $options);
        };
    }

    public function matchesCondition(mixed $userValue, mixed $condition): bool
    {
        if (is_callable($condition)) {
            return $condition($userValue);
        }

        return is_array($condition)
          ? in_array($userValue, $condition, true)
          : $userValue === $condition;
    }

    public function getAsQuestion(): ChoiceQuestion|\Symfony\Component\Console\Question\Question
    {
        if ($this->asChoice) {
            $question = $this->getChoiceQuestion();
        } else {
            $question = parent::getAsQuestion();
            $question->setAutocompleterValues($this->options);
        }

        return $question;
    }

    public function onChange(array $previousValues): void
    {
        parent::onChange($previousValues);
        if (isset($this->optionsCallback)) {
            $callback = $this->optionsCallback;
            $this->options = $callback($previousValues);
        }
    }

    protected function getChoiceQuestion(): ChoiceQuestion
    {
        $numeric = $this->isNumeric();
        $text = $this->getQuestionHeader();
        if ($numeric || $this->chooseWithNumber) {
            $text .= "\nEnter a number to choose: ";
        }
        $question = new ChoiceQuestion(
            $text,
            $this->chooseWithNumber ? array_values($this->options) : $this->options,
            $this->default
        );
        $question->setPrompt($this->prompt);
        $question->setMaxAttempts($this->maxAttempts);
        if (! $numeric) {
            $question->setAutocompleterValues(array_keys($this->options));
        }
        $question->setValidator(function ($userInput) use ($numeric) {
            if ($this->isEmpty($userInput) && $this->isRequired()) {
                if ($this->hasDefault()) {
                    return $this->default;
                }
                throw new MissingValueException("'{$this->name}' is required", $this);
            }
            if ($this->chooseWithNumber && isset(array_values($this->options)[$userInput])) {
                $userInput = array_values($this->options)[$userInput];
            }
            if (isset($this->options[$userInput])) {
                $value = $numeric ? $this->options[$userInput] : $userInput;
            } elseif (($key = array_search($userInput, $this->options, true)) !== false) {
                $value = $numeric ? $userInput : $key;
            } else {
                $value = $userInput;
            }
            $this->validate($value);

            return $value;
        });

        return $question;
    }

    protected function getDescription(): string
    {
        $description = parent::getDescription();
        $validOptions = $this->validOptions();
        if (! empty($validOptions) && $this->autoDescribe) {
            $separator = count($validOptions) === 2 ? ' or ' : ', ';
            $optionsString = "'" . implode("'{$separator}'", $this->validOptions()) . "'";
            if (strlen($optionsString) < 255) {
                $description .= ' (';
                if ($this->allowOther) {
                    $description .= 'e.g. ';
                }
                $description .= $optionsString . ')';
            }
        }

        return $description;
    }

    /**
     * Return a list of valid option values.
     */
    private function validOptions(): array
    {
        return $this->isNumeric() ? $this->options : array_keys($this->options);
    }

    /**
     * Check if this is numeric, rather than associative.
     */
    private function isNumeric(): bool
    {
        foreach (array_keys($this->options) as $key) {
            if (! is_int($key)) {
                return false;
            }
        }

        return true;
    }
}
