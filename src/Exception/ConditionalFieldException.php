<?php

declare(strict_types=1);

namespace Platformsh\ConsoleForm\Exception;

use Platformsh\ConsoleForm\Field\Field;

class ConditionalFieldException extends FieldLevelException
{
    private array $previousValues;

    public function __construct($message, Field $field, array $previousValues)
    {
        $this->previousValues = $previousValues;
        parent::__construct($message, $field);
    }

    public function getPreviousValues(): array
    {
        return $this->previousValues;
    }
}
