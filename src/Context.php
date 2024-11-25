<?php

declare(strict_types=1);

namespace Platformsh\ConsoleForm;

/**
 * Can be used to pass context around form validation, conditions, etc.
 */
class Context
{
    public bool $beforeInteraction = false;
}
