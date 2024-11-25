<?php

declare(strict_types=1);

namespace Platformsh\ConsoleForm\Field;

class FileField extends Field
{
    protected bool $requireExists = true;

    protected bool $requireReadable = true;

    protected bool $requireWritable = false;

    protected array $allowedExtensions = [];

    protected bool $contentsAsValue = false;

    public function __construct($name = 'File', array $config = [])
    {
        if (array_key_exists('allowedExtensions', $config) && ! is_array($config['allowedExtensions'])) {
            throw new \InvalidArgumentException('allowedExtensions must be an array');
        }

        parent::__construct($name, $config);

        $this->validators[] = function ($value) {
            if ($this->requireExists && ! file_exists($value)) {
                return "File not found: {$value}";
            }
            if (is_dir($value)) {
                return "The file is a directory: {$value}";
            }
            if ($this->requireExists && $this->requireReadable && ! is_readable($value)) {
                return "File not readable: {$value}";
            }
            if ($this->requireExists && $this->requireWritable && ! is_writable($value)) {
                return "File not writable: {$value}";
            }
            if (! $this->matchesAllowedExtension($value)) {
                return 'Invalid file extension (allowed: ' . implode(', ', $this->allowedExtensions) . ')';
            }

            return true;
        };
    }

    public function getFinalValue(mixed $userValue): mixed
    {
        $value = parent::getFinalValue($userValue);
        if (! empty($value) && $this->contentsAsValue) {
            $contents = file_get_contents($value);
            if ($contents === false) {
                throw new \RuntimeException('Failed to read file: ' . $value);
            }

            return $contents;
        }

        return $value;
    }

    /**
     * Checks whether the filename matches an allowed extension.
     */
    private function matchesAllowedExtension(string $filename): bool
    {
        if (empty($this->allowedExtensions)) {
            return true;
        }
        foreach ($this->allowedExtensions as $allowedExtension) {
            $normalized = '.' . ltrim($allowedExtension, '.');
            if (str_ends_with($filename, $normalized)) {
                return true;
            }
        }
        if (in_array('', $this->allowedExtensions, true) && ! str_contains($filename, '.')) {
            return true;
        }

        return false;
    }
}
