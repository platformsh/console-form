<?php

namespace Platformsh\ConsoleForm\Field;

class UrlField extends Field
{
    public function __construct($name = 'URL', array $config = [])
    {
        parent::__construct($name, $config);
        $this->validators[] = function ($value) {
            return parse_url($value, PHP_URL_HOST) ? true : "Invalid URL: $value";
        };
    }
}
