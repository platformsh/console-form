<?php

namespace Platformsh\ConsoleForm\Field;

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
        $false = ['false', '0', 'no'];

        return !in_array(strtolower($value), $false);
    }
}
