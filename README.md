# Console Form
A lightweight form system for Symfony Console commands.

Commands can define forms which can be used both via command-line options and
via interactive input.

[![Build Status](https://travis-ci.org/pjcdawkins/console-form.svg?branch=master)](https://travis-ci.org/pjcdawkins/console-form)

## Example
```php
<?php
namespace MyApplication;

use Platformsh\ConsoleForm\Field\EmailAddressField;
use Platformsh\ConsoleForm\Field\Field;
use Platformsh\ConsoleForm\Form;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MyCommand extends Command
{
    protected function configure()
    {
        $this->setName('my:command')
             ->setDescription('An example command');
        $this->form = Form::fromArray([
            'name' => new Field('Name', ['description' => 'Your full name']),
            'mail' => new EmailAddressField('Email', ['description' => 'Your email address']),
        ]);
        $this->form->configureInputDefinition($this->getDefinition());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getHelper('question');
        $result = $this->form->resolveOptions($input, $output, $questionHelper);

        $output->writeln("Your name: " . $result['name']);
        $output->writeln("Your email address: " . $result['mail']);
    }
}
```

## Alternatives

 * [Symfony Console Form](https://github.com/matthiasnoback/symfony-console-form)
