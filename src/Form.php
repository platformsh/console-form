<?php

namespace Platformsh\ConsoleForm;

use Platformsh\ConsoleForm\Field\Field;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
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
            $definition->addOption($field->getAsOption());
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
     * @return array|false
     *   An array of normalized field values, or false if any of the input was
     *   invalid. The array keys match those provided as the second argument to
     *   self::addField().
     */
    public function resolveOptions(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $valid = true;
        $values = [];
        foreach ($this->fields as $key => $field) {
            foreach ($field->getConditions() as $previousField => $condition) {
                $previousFieldObject = $this->getField($previousField);
                if ($previousFieldObject === false
                    || !isset($values[$previousField])
                    || !$previousFieldObject->matchesCondition($values[$previousField], $condition)) {
                    continue 2;
                }
            }
            $commandLineValue = $field->getValueFromInput($input, true);
            if ($commandLineValue !== null) {
                $errors = $field->validate($commandLineValue);
                if ($errors) {
                    $output->writeln($errors);
                    return false;
                }
                $values[$key] = $field->getFinalValue($commandLineValue);
            }
            else {
                $userValue = $helper->ask($input, $output, $field->getAsQuestion());
                $values[$key] = $field->getFinalValue($userValue);
            }
        }

        if (!$valid) {
            return false;
        }

        return $values;
    }
}
