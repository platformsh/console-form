<?php

namespace Platformsh\ConsoleForm\Field;

use Platformsh\ConsoleForm\Exception\InvalidValueException;

class BooleanField extends Field
{
    /**
     * @{inheritdoc}
     */
    public function __construct($name, array $config = [])
    {
        parent::__construct($name, $config);
        // The default is true, unless a callback is set.
        if (!isset($this->defaultCallback) && !isset($this->default)) {
            $this->default = $this->originalDefault = true;
        }
        $this->autoCompleterValues = ['true', 'false', 'yes', 'no'];
    }

   /**
     * {@inheritdoc}
     */
    protected function getQuestionText()
    {
        return rtrim($this->getQuestionHeader(false), '?')
          . '? [default: <question>'
          . ($this->default ? 'true' : 'false')
          . '</question>] ';
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty($value)
    {
        // False is not empty.
        if ($value === false) {
            return false;
        }

        return parent::isEmpty($value);
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
            throw new InvalidValueException(sprintf(
                "Invalid value for '%s': %s (expected 1, 0, true, or false)",
                $this->name,
                $value
            ), $this);
        }
    }
}
