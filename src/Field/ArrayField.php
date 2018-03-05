<?php

namespace Platformsh\ConsoleForm\Field;

use Symfony\Component\Console\Input\InputOption;

class ArrayField extends Field
{
    public $default = [];

    /**
     * {@inheritdoc}
     */
    public function normalize($value)
    {
        // If the value is an array of only one element, it might be a
        // comma-separated string provided to the command-line option. Extract
        // the first element.
        if (is_array($value) && count($value) === 1) {
            $value = reset($value);
        }

        // If the value is a string, split it into an array.
        if (is_string($value)) {
            $value = $this->split($value);
        }

        return parent::normalize($value);
    }

    /**
     * Split a comma or whitespace-separated string into an array.
     *
     * @param string $str
     *
     * @return array
     */
    private function split($str) {
        return array_filter(preg_split('/[,\s]+/', $str), 'strlen');
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
        if (is_callable($condition)) {
            return $condition($userValue);
        }

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
