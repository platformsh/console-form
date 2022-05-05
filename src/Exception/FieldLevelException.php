<?php

namespace Platformsh\ConsoleForm\Exception;

use Platformsh\ConsoleForm\Field\Field;

abstract class FieldLevelException extends \RuntimeException {
    protected $field;

    public function __construct($message, Field $field)
    {
        $this->field = $field;
        parent::__construct($message);
    }

    /**
     * @return Field
     */
    public function getField() {
        return $this->field;
    }
}
