<?php

namespace Platformsh\ConsoleForm\Tests;

use PHPUnit\Framework\TestCase;
use Platformsh\ConsoleForm\Exception\ConditionalFieldException;
use Platformsh\ConsoleForm\Exception\InvalidValueException;
use Platformsh\ConsoleForm\Exception\MissingValueException;
use Platformsh\ConsoleForm\Field\ArrayField;
use Platformsh\ConsoleForm\Field\BooleanField;
use Platformsh\ConsoleForm\Field\EmailAddressField;
use Platformsh\ConsoleForm\Field\Field;
use Platformsh\ConsoleForm\Field\FileField;
use Platformsh\ConsoleForm\Field\OptionsField;
use Platformsh\ConsoleForm\Field\UrlField;
use Platformsh\ConsoleForm\Form;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArgvInput;
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

    /** @var string */
    protected $validOptionsDynamicDefault = 'foo';

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
          'non_option_field' => new Field('Form-only field not being included as an option', [
            'optionName' => 'non-option-field',
            'includeAsOption' => false,
            'required' => false,
          ]),
          'with_default' => new Field('Field with default', [
            'default' => 'defaultValue',
          ]),
          'with_dynamic_default' => new Field('Field with dynamic default', [
            'defaultCallback' => function (array $values) {
                return $values['test_field'];
            },
          ]),
          'options_with_dynamic_default' => new OptionsField('Options with dynamic default', [
            'optionName' => 'options-dyn-default',
            'options' => ['foo', 'bar', 'baz'],
            'defaultCallback' => function () {
              return $this->validOptionsDynamicDefault;
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
          'url' => new UrlField('URL field', [
            'optionName' => 'url',
            'required' => false,
          ]),
          'file' => new FileField('JSON file', [
              'optionName' => 'file',
              'required' => false,
              'requireExists' => false,
              'allowedExtensions' => ['json', ''],
          ]),
          'custom_value_keys1' => new BooleanField('Field with custom value keys 1', [
            'optionName' => 'custom-keys-1',
            'default' => false,
            'valueKeys' => ['foo1'],
          ]),
          'custom_value_keys2' => new BooleanField('Field with custom value keys 2', [
            'optionName' => 'custom-keys-2',
            'default' => true,
            'valueKeys' => ['foo2', 'bar'],
          ]),
        ];
        $this->form = Form::fromArray($this->fields);
        $this->validResult = [
          'test_field' => $this->validString,
          'email' => $this->validMail,
          'bool' => true,
          'array' => [],
          'url' => null,
          'with_default' => 'defaultValue',
          'with_dynamic_default' => $this->validString,
          'options_with_dynamic_default' => $this->validOptionsDynamicDefault,
          'foo1' => false,
          'foo2' => ['bar' => true],
          'file' => null,
          'non_option_field' => null,
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
            "{$this->validString}\n{$this->validMail}\nfoo\n" . str_repeat("\n", count($this->form->getFields()) - 3)
        ));
        $result = $this->form->resolveOptions($input, $output, $helper);
        $expected = $this->validResult;
        $expected['non_option_field'] = 'foo';
        $this->assertEquals($expected, $result, 'Valid input passes');
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

        // Test providing a value for the dependent field, without making it
        // applicable yet: validating "before interaction" should pass.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--dependent' => 'value',
        ], $definition);
        $this->form->validateInputBeforeInteraction($input);

        // Test providing a value for the dependent field even though it is not
        // applicable.
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
            '--dependency' => 'doNotTrigger',
            '--dependent' => 'value',
        ], $definition);
        $this->expectException(ConditionalFieldException::class);
        $this->form->validateInputBeforeInteraction($input);
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

    public function testOptionsFieldWithDynamicDefault()
    {
        // Test dynamic default.
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);
        $input = new ArrayInput([
            '--test' => $this->validString,
            '--mail' => $this->validMail,
        ], $definition);
        $input->setInteractive(false);
        $original = $this->validOptionsDynamicDefault;
        $this->validOptionsDynamicDefault = 'baz';
        $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
        $this->validOptionsDynamicDefault = $original;
        $validResult = $this->validResult;
        $validResult['options_with_dynamic_default'] = 'baz';
        $this->assertEquals($validResult, $result, 'Dynamic option default worked');
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

    public function testCommandLineCommaSeparatedArrayOptions()
    {
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);

        $validResult = $this->validResult;
        $validResult['array'] = ['foo', 'bar', 'baz'];

        $input = new ArgvInput([
            'commandName',
            '--test', $this->validString,
            '--mail', $this->validMail,
            '--array', 'foo, bar,baz',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
        $this->assertEquals($validResult, $result, 'Array input with comma-separated values passes');

        $validResult = $this->validResult;
        $validResult['array'] = ['foo, bar', 'baz'];

        $input = new ArgvInput([
            'commandName',
            '--test', $this->validString,
            '--mail', $this->validMail,
            '--array', 'foo, bar',
            '--array', 'baz',
        ], $definition);
        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
        $this->assertEquals($validResult, $result, 'Array input with array values passes');
    }

    public function testUrlField()
    {
      $definition = new InputDefinition();
      $this->form->configureInputDefinition($definition);

      $validResult = $this->validResult;
      $validResult['url'] = 'https://example.com';

      $input = new ArgvInput([
        'commandName',
        '--test', $this->validString,
        '--mail', $this->validMail,
        '--url', 'https://example.com',
      ], $definition);
      $input->setInteractive(false);
      $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
      $this->assertEquals($validResult, $result, 'URL input with valid URL passes');

      $input = new ArgvInput([
        'commandName',
        '--test', $this->validString,
        '--mail', $this->validMail,
        '--url', 'example.com',
      ], $definition);
      $input->setInteractive(false);
      $this->expectException(InvalidValueException::class);
      $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
      $this->assertEquals($validResult, $result, 'URL input with invalid URL fails');
    }

    public function testPresetInputOptions()
    {
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);

        $validResult = $this->validResult;

        $input = new ArgvInput([
            'commandName',
            '--test', $this->validString,
            '--mail', $this->validMail,
        ], $definition);

        $input->setOption('field-with-default', 'test string');
        $validResult['with_default'] = 'test string';

        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
        $this->assertEquals($validResult, $result, 'Input with non-parameter value passes.');
    }

    public function testFileField()
    {
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);

        $validResult = $this->validResult;

        $input = new ArgvInput([
            'commandName',
            '--test', $this->validString,
            '--mail', $this->validMail,
            '--file', 'filename.json',
        ], $definition);

        $validResult['file'] = 'filename.json';

        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
        $this->assertEquals($validResult, $result, 'Input with valid filename passes.');

        $input = new ArgvInput([
            'commandName',
            '--test', $this->validString,
            '--mail', $this->validMail,
            '--file', 'filename',
        ], $definition);

        $validResult['file'] = 'filename';

        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
        $this->assertEquals($validResult, $result, 'Filename with no extension considered valid.');

        $input = new ArgvInput([
            'commandName',
            '--test', $this->validString,
            '--mail', $this->validMail,
            '--file', 'filename.png',
        ], $definition);

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Invalid file extension');
        $input->setInteractive(false);
        $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
    }

    public function testFileContentsAsValue()
    {
        $definition = new InputDefinition();
        $this->form->configureInputDefinition($definition);

        $this->form->getField('file')->set('contentsAsValue', true);

        $tmpFilename = tempnam(sys_get_temp_dir(), 'test');
        $testContents = function_exists('random_bytes') ? random_bytes(24) : rand(1000, 1000000000);
        file_put_contents($tmpFilename, $testContents);

        $input = new ArgvInput([
            'commandName',
            '--test', $this->validString,
            '--mail', $this->validMail,
            '--file', $tmpFilename,
        ], $definition);

        $validResult['file'] = '';

        $input->setInteractive(false);
        $result = $this->form->resolveOptions($input, new NullOutput(), $this->getQuestionHelper());
        $this->assertEquals($testContents, $result['file'], "Value for file is returned as the file's contents");
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
