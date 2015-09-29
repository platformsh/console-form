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
            return is_array($value) ? $value : preg_split('/[,;\n] */', $value);
        });

        return $question;
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
}
