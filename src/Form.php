<?php

namespace Platformsh\ConsoleForm;

use Platformsh\ConsoleForm\Exception\ConditionalFieldException;
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
     * Validates command-line input, partially, without using interaction.
     *
     * @param InputInterface $input
     *
     * @return void
     */
    public function validateInputBeforeInteraction(InputInterface $input)
    {
        $values = [];
        foreach ($this->fields as $key => $field) {
            $field->onChange($values);

            if (!$this->includeField($field, $values, true)) {
                if ($field->getValueFromInput($input, false) !== null) {
                    throw new ConditionalFieldException('--' . $field->getOptionName() . ' is not applicable', $field, $values);
                }
                continue;
            }

            $value = $field->getValueFromInput($input, false);
            if ($value !== null) {
                $field->validate($value, true);
            }

            self::setNestedArrayValue(
                $values,
                $field->getValueKeys() ?: [$key],
                $field->getFinalValue($value),
                true
            );
        }
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
     * @param Context|null $context
     *
     * @throws InvalidValueException if any of the input was invalid.
     *
     * @return array
     *   An array of normalized field values. The array keys match those
     *   provided as the second argument to self::addField().
     */
    public function resolveOptions(InputInterface $input, OutputInterface $output, QuestionHelper $helper, Context $context = null)
    {
        $context = $context ?: new Context();
        try {
            $context->beforeInteraction = true;
            $this->validateInputBeforeInteraction($input);
        } finally {
            $context->beforeInteraction = false;
        }

        $values = [];
        $stdErr = $output instanceof ConsoleOutput ? $output->getErrorOutput() : $output;
        foreach ($this->fields as $key => $field) {
            $field->onChange($values);

            if (!$this->includeField($field, $values)) {
                if ($field->getValueFromInput($input, false) !== null) {
                    throw new ConditionalFieldException('--' . $field->getOptionName() . ' is not applicable', $field, $values);
                }
                continue;
            }

            // Get the value from the command-line options.
            $value = $field->getValueFromInput($input, false);
            if ($value !== null) {
                $field->validate($value, true);
            } elseif ($input->isInteractive() && $field->shouldAskAsQuestion()) {
                // Get the value interactively.
                $value = $helper->ask($input, $stdErr, $field->getAsQuestion());
                $stdErr->writeln('');
            } elseif ($field->isRequired() && !$field->hasDefault()) {
                throw new MissingValueException('--' . $field->getOptionName() . ' is required', $field);
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
     * @param bool  $ignoreUnsetValues
     *
     * @return bool
     */
    public function includeField(Field $field, array $previousValues, $ignoreUnsetValues = false)
    {
        foreach ($field->getConditions() as $previousField => $condition) {
            $previousFieldObject = $this->getField($previousField);
            if ($previousFieldObject === false || !isset($previousValues[$previousField])) {
                if ($ignoreUnsetValues) {
                    continue;
                }
                return false;
            }
            if (!$previousFieldObject->matchesCondition($previousValues[$previousField], $condition)) {
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
