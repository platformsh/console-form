<?php

declare(strict_types=1);

namespace Platformsh\ConsoleForm\Exception;

use Platformsh\ConsoleForm\Field\Field;

abstract class FieldLevelException extends \RuntimeException
{
    protected Field $field;

    public function __construct($message, Field $field)
    {
        $this->field = $field;
        parent::__construct($message);
    }

    public function getField(): Field
    {
        return $this->field;
    }
}
