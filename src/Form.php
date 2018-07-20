<?php

namespace Platformsh\ConsoleForm;

use Platformsh\ConsoleForm\Exception\InvalidValueException;
use Platformsh\ConsoleForm\Exception\MissingValueException;
use Platformsh\ConsoleForm\Field\Field;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Form
{
    /** @var Field[] */
    protected $fields = [];

    /**
     * Add a field to the form.
     *
     * @param Field $field
     * @param string $key
     *
     * @return $this
     */
    public function addField(Field $field, $key = null)
    {
        $this->fields[$key] = $field;

        return $this;
    }

    /**
     * Get a single form field.
     *
     * @param string $key
     *
     * @return Field|false
     */
    public function getField($key)
    {
        if (!isset($this->fields[$key])) {
            return false;
        }

        return $this->fields[$key];
    }

    /**
     * Create a form from an array of fields.
     *
     * @param Field[] $fields
     *
     * @return static
     */
    public static function fromArray(array $fields)
    {
        $form = new static();
        foreach ($fields as $key => $field) {
            $form->addField($field, $key);
        }

        return $form;
    }

    /**
     * Add options to a Symfony Console input definition.
     *
     * @param InputDefinition $definition
     */
    public function configureInputDefinition(InputDefinition $definition)
    {
        foreach ($this->fields as $field) {
            if ($field->includeAsOption()) {
                $definition->addOption($field->getAsOption());
            }
        }
    }

    /**
     * Get all the form fields.
     *
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Validate specified options, and ask questions for any missing values.
     *
     * Values can come from three sources at the moment:
     *  - command-line input
     *  - defaults
     *  - interactive questions
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $helper
     *
     * @throws InvalidValueException if any of the input was invalid.
     *
     * @return array
     *   An array of normalized field values. The array keys match those
     *   provided as the second argument to self::addField().
     */
    public function resolveOptions(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $values = [];
        $stdErr = $output instanceof ConsoleOutput ? $output->getErrorOutput() : $output;
        foreach ($this->fields as $key => $field) {
            $field->onChange($values);

            if (!$this->includeField($field, $values)) {
                continue;
            }

            // Get the value from the command-line options.
            $value = $field->getValueFromInput($input, false);
            if ($value !== null) {
                $field->validate($value);
            } elseif ($input->isInteractive()) {
                // Get the value interactively.
                $value = $helper->ask($input, $stdErr, $field->getAsQuestion());
                $stdErr->writeln('');
            } elseif ($field->isRequired()) {
                throw new MissingValueException('--' . $field->getOptionName() . ' is required');
            }

            self::setNestedArrayValue(
                $values,
                $field->getValueKeys() ?: [$key],
                $field->getFinalValue($value),
                true
            );
        }

        return $values;
    }

    /**
     * Determine whether the field should be included.
     *
     * @param Field $field
     * @param array $previousValues
     *
     * @return bool
     */
    public function includeField(Field $field, array $previousValues)
    {
        foreach ($field->getConditions() as $previousField => $condition) {
            $previousFieldObject = $this->getField($previousField);
            if ($previousFieldObject === false
                || !isset($previousValues[$previousField])
                || !$previousFieldObject->matchesCondition($previousValues[$previousField], $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set a nested value in an array.
     *
     * @see Copied from \Drupal\Component\Utility\NestedArray::setValue()
     *
     * @param array &$array
     * @param array $parents
     * @param mixed $value
     * @param bool  $force
     */
    public static function setNestedArrayValue(array &$array, array $parents, $value, $force = false)
    {
        $ref = &$array;
        foreach ($parents as $parent) {
            // PHP auto-creates container arrays and NULL entries without error if $ref
            // is NULL, but throws an error if $ref is set, but not an array.
            if ($force && isset($ref) && !is_array($ref)) {
                $ref = [];
            }
            $ref = &$ref[$parent];
        }
        $ref = $value;
    }
}
