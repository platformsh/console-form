<?php

namespace Platformsh\ConsoleForm\Tests;

use PHPUnit\Framework\TestCase;
use Platformsh\ConsoleForm\Exception\InvalidValueException;
use Platformsh\ConsoleForm\Exception\MissingValueException;
use Platformsh\ConsoleForm\Field\ArrayField;
use Platformsh\ConsoleForm\Field\BooleanField;
use Platformsh\ConsoleForm\Field\EmailAddressField;
use Platformsh\ConsoleForm\Field\Field;
use Platformsh\ConsoleForm\Field\OptionsField;
use Platformsh\ConsoleForm\Form;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\NullOutput;

class FormTest extends TestCase
{
    /** @var Form */
    protected $form;

    /** @var array */
    protected $fields = [];

    /** @var string */
    protected $validString = 'validString';

    /** @var string */
    protected $validMail = 'valid@example.com';

    /** @var array */
    protected $validResult = [];

    protected function setUp()
    {
        $this->fields = [
          'test_field' => new Field('Test field', [
            'optionName' => 'test',
            'validator' => function ($value) {
                return $value === $this->validString;
            },
          ]),
          'email' => new EmailAddressField('Email address', [
            'optionName' => 'mail',
          ]),
          'with_default' => new Field('Field with default', [
            'default' => 'defaultValue',
          ]),
          'with_dynamic_default' => new Field('Field with dynamic default', [
            'defaultCallback' => function (array $values) {
                return $values['test_field'];
            },
          ]),
          'bool' => new BooleanField('Boolean field', [
            'optionName' => 'bool',
            'required' => false,
          ]),
          'array' => new ArrayField('Array field', [
            'optionName' => 'array',
            'required' => false,
          ]),
        ];
        $this->form = Form::fromArray($this->fields);
        $this->validResult = [
          'test_field' => $this->validString,
          'email' => $this->validMail,
          'bool' => true,
          'array' => [],
          'with_default' => 'defaultValue',
          'with_dynamic_default' => $this->validString,
        ];
    }

    public function testNonInteractiveInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals($this->validResult, $result, 'Valid input passes');

        $input = new ArrayInput([
          '--test' => 'invalidString',
          '--mail' => 'invalidMail',
        ], $definition);
        $input->setInteractive(false);
        $this->expectException('\\Platformsh\\ConsoleForm\\Exception\\InvalidValueException');
        $this->form->resolveOptions($input, $output, $helper);

        $input = new ArrayInput([
            '--test' => $this->validString,
        ], $definition);
        $input->setInteractive(false);
        $this->expectException(MissingValueException::class);
        $this->expectExceptionMessage('--mail is required');
        $this->form->resolveOptions($input, $output, $helper);
    }

    public function testWrongInteractiveInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $input = new ArrayInput([], $definition);
        $output = new NullOutput();

        $this->expectException(MissingValueException::class);
        $this->expectExceptionMessage("'Test field' is required");
        $maxAttempts = 5;
        $input->setStream($this->getInputStream(str_repeat("\n", $maxAttempts)));
        $this->form->resolveOptions($input, $output, $helper);
    }

    public function testCorrectInteractiveInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $input = new ArrayInput([], $definition);
        $output = new NullOutput();

        $input->setStream($this->getInputStream(
            "{$this->validString}\n{$this->validMail}\n" . str_repeat("\n", count($this->form->getFields()) - 2)
        ));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals($this->validResult, $result, 'Valid input passes');
    }

    public function testMixedInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        $input = new ArrayInput(['--mail' => $this->validMail], $definition);
        $input->setStream($this->getInputStream(
          "{$this->validString}\n" .  str_repeat("\n", count($this->form->getFields()) - 1)
        ));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals($this->validResult, $result, 'Valid input passes');

        $this->expectException(InvalidValueException::class);
        $input = new ArrayInput(['--test' => 'invalidString'], $definition);
        $input->setStream($this->getInputStream("{$this->validMail}\n"));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals(false, $result, 'Invalid input fails');
    }

    public function testNormalizedInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->addField(new Field('Normalized field', [
            'optionName' => 'to-upper',
            'description' => 'Input will be changed to upper case',
            'normalizer' => 'strtoupper',
            'required' => false,
        ]), 'to_upper');
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        $input = new ArrayInput([
          '--test' => $this->validString,
          '--mail' => $this->validMail,
          '--to-upper' => 'testString',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + ['to_upper' => 'TESTSTRING'];
        $this->assertEquals($validResult, $result, 'Input has been normalized');
    }

    public function testDependentField()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->addField(new Field('Dependency'), 'dependency');
        $this->form->addField(new Field('Dependent', [
            'conditions' => ['dependency' => 'doTrigger'],
        ]), 'dependent');
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        // Test without triggering the dependent field.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--dependency' => 'doNotTrigger',
            '--dependent' => 'value',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + [
            'dependency' => 'doNotTrigger'
        ];
        $this->assertEquals($validResult, $result, 'Dependent field does not appear');

        // Test triggering the dependent field and providing a value.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--dependency' => 'doTrigger',
            '--dependent' => 'value',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + [
            'dependency' => 'doTrigger',
            'dependent' => 'value',
        ];
        $this->assertEquals($validResult, $result, 'Dependent field does appear');

        // Test triggering the dependent field and not providing a value.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--dependency' => 'doTrigger',
        ], $definition);
        $input->setInteractive(false);
        $this->expectException(MissingValueException::class);
        $this->expectExceptionMessage('--dependent is required');
        $this->form->resolveOptions($input, $output, $helper);
    }

    public function testDependentOnOptionsField()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->addField(new OptionsField('Dependency', [
            'options' => ['doTrigger', 'doTrigger2', 'doNotTrigger'],
        ]), 'dependency');
        $this->form->addField(new Field('Dependent', [
            'conditions' => ['dependency' => ['doTrigger', 'doTrigger2']],
        ]), 'dependent');
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        // Test without triggering the dependent field.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--dependency' => 'doNotTrigger',
            '--dependent' => 'value',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + ['dependency' => 'doNotTrigger'];
        $this->assertEquals($validResult, $result, 'Dependent field does not appear');

        // Test triggering the dependent field and providing a value.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--dependency' => 'doTrigger',
            '--dependent' => 'value',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + [
                'dependency' => 'doTrigger',
                'dependent' => 'value',
            ];
        $this->assertEquals($validResult, $result, 'Dependent field does appear');
    }

    public function testOptionsField()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $countFieldsBefore = count($this->form->getFields());
        $this->form->addField(new OptionsField('Options', [
            'options' => ['option1', 'option2', 'option3'],
        ]), 'options');
        $this->form->addField(new OptionsField('Non-strict options', [
            'options' => ['optionA', 'optionB', 'optionC'],
            'allowOther' => true,
            'optionName' => 'options-allow-other',
        ]), 'options_non_strict');
        $this->form->addField(new OptionsField('Associative options', [
            'options' => ['option1' => 'Option 1', 'option2' => 'Option 2', 'option3' => 'Option 3'],
            'optionName' => 'options-assoc',
        ]), 'options-assoc');
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        // Test non-interactive input.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--options' => 'option1',
            '--options-allow-other' => 'optionO',
            '--options-assoc' => 'option2',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + [
            'options' => 'option1',
            'options_non_strict' => 'optionO',
            'options-assoc' => 'option2',
        ];
        $this->assertEquals($validResult, $result, 'Valid non-interactive option input');

        // Test interactive input.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--options-allow-other' => 'optionO',
        ], $definition);
        $input->setStream($this->getInputStream(str_repeat("\n", $countFieldsBefore) . "1\noption2"));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + [
            'options' => 'option2',
            'options_non_strict' => 'optionO',
            'options-assoc' => 'option2',
        ];
        $this->assertEquals($validResult, $result, 'Valid interactive option input');
    }

    public function testCustomValidator()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->addField(new Field('Test field', [
            'optionName' => 'custom-validated',
            'validator' => function ($value) {
                return $value === 'valid' ? true : 'Not valid';
            },
        ]), 'custom_validated');
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--custom-validated' => 'valid',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + ['custom_validated' => 'valid'];
        $this->assertEquals($validResult, $result);

        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--custom-validated' => 'not valid',
        ], $definition);
        $input->setInteractive(false);
        $this->expectException('\\Platformsh\\ConsoleForm\\Exception\\InvalidValueException');
        $this->expectExceptionMessage('Not valid');
        $this->form->resolveOptions($input, $output, $helper);
    }

    public function testInvalidConfig()
    {
        $this->expectException('InvalidArgumentException');
        new Field('Test field', ['invalid' => 'invalid']);
    }

    public function testOverrideField()
    {
      $fields = $this->fields;
      $validResult = $this->validResult;
      $validResult['test_field'] = 'Default result for test_field';
      $validResult['with_dynamic_default'] = $validResult['test_field'];
      $validResult['email'] = 'test-default@example.com';
      $fields['test_field']->set('default', $validResult['test_field']);
      $fields['email']->set('default', $validResult['email']);

      $definition = new InputDefinition();
      $this->form = Form::fromArray($fields);
      $this->form->configureInputDefinition($definition);

      $input = new ArrayInput([], $definition);
      $input->setInteractive(false);
      $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
      $this->assertEquals($validResult, $result, 'Empty input passes');
    }

    /**
     * @return QuestionHelper
     */
    protected function getQuestionHelper()
    {
        $questionHelper = new QuestionHelper();

        $helperSet = new HelperSet([new FormatterHelper()]);
        $questionHelper->setHelperSet($helperSet);

        return $questionHelper;
    }

    /**
     * @param string $input
     *
     * @return resource
     */
    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fwrite($stream, $input);
        rewind($stream);

        return $stream;
    }
}
