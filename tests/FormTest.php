<?php

namespace Platformsh\ConsoleForm\Tests;

use Platformsh\ConsoleForm\Field\ArrayField;
use Platformsh\ConsoleForm\Field\BooleanField;
use Platformsh\ConsoleForm\Field\EmailAddressField;
use Platformsh\ConsoleForm\Field\Field;
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
          'to_upper' => new Field('Normalized field', [
            'optionName' => 'to-upper',
            'description' => 'Input will be changed to upper case',
            'normalizer' => 'strtoupper',
            'required' => false,
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
          'to_upper' => null,
          'bool' => true,
          'array' => [],
        ];
    }

    public function testNonInteractiveInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        $input = new ArrayInput([
          '--test' => 'invalidString',
          '--mail' => 'invalidMail',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertFalse($result, 'Invalid input fails');

        $input = new ArrayInput([
          '--test' => $this->validString,
          '--mail' => $this->validMail,
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals($this->validResult, $result, 'Valid input passes');
    }

    public function testWrongInteractiveInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $input = new ArrayInput([], $definition);
        $output = new NullOutput();

        $this->setExpectedException('RuntimeException', 'is required');
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

        $input = new ArrayInput(['--test' => 'invalidString'], $definition);
        $helper->setInputStream($this->getInputStream("{$this->validMail}\n"));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals(false, $result, 'Invalid input fails');

        $input = new ArrayInput(['--mail' => $this->validMail], $definition);
        $helper->setInputStream($this->getInputStream(
          "{$this->validString}\n" .  str_repeat("\n", count($this->fields) - 1)
        ));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $this->assertEquals($this->validResult, $result, 'Valid input passes');
    }

    public function testNormalizedInput()
    {
        $helper = $this->getQuestionHelper();
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $output = new NullOutput();

        $input = new ArrayInput([
          '--test' => $this->validString,
          '--mail' => $this->validMail,
          '--to-upper' => 'testString',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, $output, $helper);
        $validResult = $this->validResult;
        $validResult['to_upper'] = 'TESTSTRING';
        $this->assertEquals($validResult, $result, 'Input has been normalized');
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
