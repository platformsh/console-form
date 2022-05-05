<?php

namespace Platformsh\ConsoleForm\Exception;

use Platformsh\ConsoleForm\Field\Field;

class ConditionalFieldException extends FieldLevelException {
    private $previousValues;

    public function __construct($message, Field $field, array $previousValues)
    {
        $this->previousValues = $previousValues;
        parent::__construct($message, $field);
    }

    /**
     * @return array
     */
    public function getPreviousValues()
    {
        return $this->previousValues;
    }
}
