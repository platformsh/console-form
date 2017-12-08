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
        $text = $this->getQuestionHeader(false);
        if (!empty($this->default)) {
            $text .= "\n" . 'Default: <question>' . implode(', ', (array) $this->default) . '</question>';
        }
        $text .= "\nEnter comma-separated values";
        if (!$this->isRequired()) {
            $text .= ' (or leave this blank)';
        }
        $text .= "\n" . $this->prompt;

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
