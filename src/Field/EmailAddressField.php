<?php

namespace Platformsh\ConsoleForm\Field;

class EmailAddressField extends Field
{
    public function __construct($name = 'Email', array $config = [])
    {
        parent::__construct($name, $config);
        $this->validators[] = function ($value) {
            return filter_var($value, FILTER_VALIDATE_EMAIL) ? true : "Invalid email address: $value";
        };
    }
}
