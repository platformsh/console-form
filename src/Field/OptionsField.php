<?php

namespace Platformsh\ConsoleForm\Field;

class OptionsField extends Field
{
    protected $options = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($name, array $config = [])
    {
        parent::__construct($name, $config);
        $this->validators[] = function ($value) {
            return in_array($value, $this->options, true)
                ? true : "$value is not one of: " . implode(', ', $this->options);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function matchesCondition($userValue, $condition)
    {
        return is_array($userValue)
          ? in_array($userValue, $condition)
          : $userValue === $condition;
    }

    /**
     * {@inheritdoc}
     */
    public function getAsQuestion()
    {
        // Set up auto-completion for the question.
        $question = parent::getAsQuestion();
        $question->setAutocompleterValues($this->options);

        return $question;
    }
}
