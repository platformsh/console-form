<?php

namespace Platformsh\ConsoleForm\Field;

use Symfony\Component\Console\Question\ChoiceQuestion;

class OptionsField extends Field
{
    protected $options = [];
    protected $asChoice = true;
    protected $allowOther = false;

    /**
     * A callback used to calculate dynamic options.
     *
     * The callback accepts an array of values entered previously for other form
     * fields. It returns the new options, as an array.
     *
     * @var callable
     */
    protected $optionsCallback;

    /**
     * {@inheritdoc}
     */
    public function __construct($name, array $config = [])
    {
        parent::__construct($name, $config);
        $this->validators[] = function ($value) {
            if ($this->allowOther) {
                return true;
            }
            $options = $this->validOptions();

            return array_search($value, $options, true) !== false
                ? true : "$value is not one of: " . implode(', ', $options);
        };
    }

    /**
     * Return a list of valid option values.
     *
     * @return array
     */
    private function validOptions()
    {
        return $this->isNumeric() ? $this->options : array_keys($this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function matchesCondition($userValue, $condition)
    {
        if (is_callable($condition)) {
            return $condition($userValue);
        }

        return is_array($condition)
          ? in_array($userValue, $condition)
          : $userValue === $condition;
    }

    /**
     * {@inheritdoc}
     */
    public function getAsQuestion()
    {
        if ($this->asChoice) {
            $question = $this->getChoiceQuestion();
        }
        else {
            $question = parent::getAsQuestion();
            $question->setAutocompleterValues($this->options);
        }

        return $question;
    }

    /**
     * {@inheritdoc}
     */
    protected function getChoiceQuestion()
    {
        $numeric = $this->isNumeric();
        $text = $this->getQuestionHeader();
        if ($numeric) {
            $text .= "\nEnter a number to choose: ";
        }

        $question = new ChoiceQuestion(
            $text,
            $this->options,
            $this->default
        );
        $question->setPrompt($this->prompt);
        $question->setMaxAttempts($this->maxAttempts);
        if (!$numeric) {
            $question->setAutocompleterValues(array_keys($this->options));
        }

        return $question;
    }

    /**
     * @return string
     */
    protected function getDescription()
    {
        $description = parent::getDescription();
        $validOptions = $this->validOptions();
        if (!empty($validOptions)) {
            $separator = count($validOptions) === 2 ? ' or ' : ', ';
            $optionsString = "'" . implode("'$separator'", $this->validOptions()) . "'";
            if (strlen($optionsString) < 255) {
                $description .= ' (' . $optionsString . ')';
            }
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    public function onChange(array $previousValues)
    {
        if (isset($this->optionsCallback)) {
            $callback = $this->optionsCallback;
            $this->options = $callback($previousValues);
        }
    }

    /**
     * Check if this is numeric, rather than associative.
     *
     * @return bool
     */
    private function isNumeric()
    {
        foreach (array_keys($this->options) as $key) {
            if (!is_int($key)) {
                return false;
            }
        }

        return true;
    }
}
