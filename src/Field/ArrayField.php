<?php

declare(strict_types=1);

namespace Platformsh\ConsoleForm\Field;

use Platformsh\ConsoleForm\Exception\MissingValueException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;

class ArrayField extends Field
{
    public const SPLIT_PATTERN_COMMA_WHITESPACE = '/[,\s]+/';

    public const SPLIT_PATTERN_COMMA_NEWLINE = '/[,\r\n]+/';

    public const SPLIT_PATTERN_NEWLINE = '/[\r\n]+/';

    public mixed $default = [];

    protected string $splitPattern = self::SPLIT_PATTERN_COMMA_WHITESPACE;

    public function normalize(mixed $value): array
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

    public function getAsQuestion(): Question
    {
        $question = new Question($this->getQuestionText(), $this->default ? implode(', ', $this->default) : null);
        $question->setMaxAttempts($this->maxAttempts);
        $question->setValidator(function ($value) {
            if ($this->isEmpty($value) && $this->isRequired()) {
                throw new MissingValueException("'{$this->name}' is required", $this);
            }
            $this->validate($value);

            return $value;
        });
        $question->setAutocompleterValues($this->autoCompleterValues);

        return $question;
    }

    public function matchesCondition(mixed $userValue, mixed $condition): bool
    {
        if (is_callable($condition)) {
            return $condition($userValue);
        }

        return ! array_diff($userValue, $condition);
    }

    public function getOptionMode(): int
    {
        return InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED;
    }

    public function isEmpty(mixed $value): bool
    {
        return empty($value);
    }

    public function isRequired(): bool
    {
        return $this->required && empty($this->default);
    }

    protected function getQuestionText(): string
    {
        $text = $this->getQuestionHeader(false);
        if (! empty($this->default)) {
            $text .= "\n" . 'Default: <question>' . implode(', ', (array) $this->default) . '</question>';
        }
        if ($this->splitPattern === self::SPLIT_PATTERN_COMMA_NEWLINE || $this->splitPattern === self::SPLIT_PATTERN_COMMA_WHITESPACE) {
            $text .= "\nEnter comma-separated values";
            if (! $this->isRequired()) {
                $text .= ' (or leave this blank)';
            }
        }
        $text .= "\n" . $this->prompt;

        return $text;
    }

    /**
     * Split a comma or whitespace-separated string into an array.
     */
    private function split(string $str): array
    {
        return array_filter(preg_split($this->splitPattern, $str), 'strlen');
    }
}
