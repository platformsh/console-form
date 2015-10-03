<?php

namespace Platformsh\ConsoleForm\Tests;

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

class FormTest extends \PHPUnit_Framework_TestCase
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
        $this->setExpectedException('\\Platformsh\\ConsoleForm\\Exception\\InvalidValueException');
        $this->form->resolveOptions($input, $output, $helper);

        $input = new ArrayInput([
            '--test' => $this->validString,
        ], $definition);
        $input->setInteractive(false);
        $this->setExpectedException(
            '\\Platformsh\\ConsoleForm\\Exception\\MissingValueException',
            '--mail is required'
        );
        $this->form->resolveOptions($input, $output, $helper);
    }

    public function testWrongInteractiveInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $input = new ArrayInput([], $definition);
        $output = new NullOutput();

        $this->setExpectedException(
            '\\Platformsh\\ConsoleForm\\Exception\\MissingValueException',
            "'Test field' is required"
        );
        $maxAttempts = 5;
        $helper->setInputStream($this->getInputStream(str_repeat("\n", $maxAttempts)));
        $this->form->resolveOptions($input, $output, $helper);
    }

    public function testCorrectInteractiveInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $input = new ArrayInput([], $definition);
        $output = new NullOutput();

        $helper->setInputStream($this->getInputStream(
          "{$this->validString}\n{$this->validMail}\n" . str_repeat("\n", count($this->fields) - 2)
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
        $helper->setInputStream($this->getInputStream(
          "{$this->validString}\n" .  str_repeat("\n", count($this->fields) - 1)
        ));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals($this->validResult, $result, 'Valid input passes');

        $this->setExpectedException('\\Platformsh\\ConsoleForm\\Exception\\InvalidValueException');
        $input = new ArrayInput(['--test' => 'invalidString'], $definition);
        $helper->setInputStream($this->getInputStream("{$this->validMail}\n"));
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
        $this->setExpectedException(
            '\\Platformsh\\ConsoleForm\\Exception\\MissingValueException',
            '--dependent is required'
        );
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
        $this->form->addField(new OptionsField('Options', [
            'options' => ['option1', 'option2', 'option3'],
        ]), 'options');
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        // Test non-interactive input.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--options' => 'option1',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + ['options' => 'option1'];
        $this->assertEquals($validResult, $result, 'Valid non-interactive option input');

        // Test interactive input.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
        ], $definition);
        $helper->setInputStream($this->getInputStream("\n\n\n1"));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult + ['options' => 'option2'];
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
        $this->setExpectedException('\\Platformsh\\ConsoleForm\\Exception\\InvalidValueException', 'Not valid');
        $this->form->resolveOptions($input, $output, $helper);
    }

    public function testInvalidConfig()
    {
        $this->setExpectedException('InvalidArgumentException');
        new Field('Test field', ['invalid' => 'invalid']);
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
