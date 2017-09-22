<?php

namespace Platformsh\ConsoleForm\Field;

use Symfony\Component\Console\Input\InputOption;

class ArrayField extends Field
{
    public $default = [];

    /**
     * {@inheritdoc}
     */
    public function getAsQuestion()
    {
        $question = parent::getAsQuestion();
        $question->setNormalizer(function ($value) {
            return is_array($value) ? $value : array_filter(preg_split('/[,;\n] */', $value), 'strlen');
        });

        return $question;
    }

    /**
     * {@inheritdoc}
     */
    protected function getQuestionText()
    {
        $text = $this->name;
        if (!empty($this->default)) {
            $text .= ' <question>[default: ' . $this->formatDefault($this->default) . ']</question>';
        } else {
            $text .= ' <question>[comma-separated]</question>';
        }
        $text .= ': ';

        return $text;
    }

    /**
     * {@inheritdoc}
     */
    public function matchesCondition($userValue, $condition)
    {
        return !array_diff($userValue, $condition);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptionMode()
    {
        return InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty($value)
    {
        return empty($value);
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired()
    {
        return $this->required && empty($this->default);
    }
}
