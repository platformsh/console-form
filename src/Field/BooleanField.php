<?php

namespace Platformsh\ConsoleForm\Field;

use Platformsh\ConsoleForm\Exception\InvalidValueException;

class BooleanField extends Field
{
    public $default = true;

    /**
     * {@inheritdoc}
     */
    protected function getQuestionText()
    {
        return $this->name
          . '? <question>['
          . ($this->default ? 'Y|n' : 'y|N')
          . ']</question> ';
    }

    /**
     * {@inheritdoc}
     */
    protected function normalize($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        elseif (preg_match('/^(0|false|no|n)$/i', $value)) {
            return false;
        }
        elseif (preg_match('/^(1|true|yes|y)$/i', $value)) {
            return true;
        }
        else {
            throw new InvalidValueException("Invalid value for '{$this->name}': $value");
        }
    }
}
