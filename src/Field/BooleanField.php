<?php

declare(strict_types=1);

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
        if (! isset($this->defaultCallback) && ! isset($this->default)) {
            $this->default = $this->originalDefault = true;
        }
        $this->autoCompleterValues = ['true', 'false', 'yes', 'no'];
    }

    public function isEmpty(mixed $value): bool
    {
        // False is not empty.
        if ($value === false) {
            return false;
        }

        return parent::isEmpty($value);
    }

    protected function getQuestionText(): string
    {
        return rtrim($this->getQuestionHeader(false), '?')
          . '? [default: <question>'
          . ($this->default ? 'true' : 'false')
          . '</question>] ';
    }

    protected function normalize(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        } elseif (preg_match('/^(0|false|no|n)$/i', $value)) {
            return false;
        } elseif (preg_match('/^(1|true|yes|y)$/i', $value)) {
            return true;
        }

        throw new InvalidValueException(sprintf(
            "Invalid value for '%s': %s (expected 1, 0, true, or false)",
            $this->name,
            $value
        ), $this);

    }
}
