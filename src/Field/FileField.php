<?php

namespace Platformsh\ConsoleForm\Field;

class FileField extends Field
{
    protected $requireExists = true;
    protected $requireReadable = true;
    protected $requireWritable = false;
    protected $allowedExtensions = [];
    protected $contentsAsValue = false;

    public function __construct($name = 'File', array $config = [])
    {
        if (array_key_exists('allowedExtensions', $config) && !is_array($config['allowedExtensions'])) {
            throw new \InvalidArgumentException('allowedExtensions must be an array');
        }

        parent::__construct($name, $config);

        $this->validators[] = function ($value) {
            if ($this->requireExists && !file_exists($value)) {
                return "File not found: $value";
            }
            if (is_dir($value)) {
                return "The file is a directory: $value";
            }
            if ($this->requireExists && $this->requireReadable && !is_readable($value)) {
                return "File not readable: $value";
            }
            if ($this->requireExists && $this->requireWritable && !is_writable($value)) {
                return "File not writable: $value";
            }
            if (!$this->matchesAllowedExtension($value)) {
                return "Invalid file extension (allowed: " . implode(', ', $this->allowedExtensions) . ")";
            }

            return true;
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getFinalValue($userValue)
    {
        $value = parent::getFinalValue($userValue);
        if (!empty($value) && $this->contentsAsValue) {
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
     *
     * @param string $filename
     *
     * @return bool
     */
    private function matchesAllowedExtension($filename)
    {
        if (empty($this->allowedExtensions)) {
            return true;
        }
        foreach ($this->allowedExtensions as $allowedExtension) {
            $normalized = '.' . ltrim($allowedExtension, '.');
            if (substr($filename, - strlen($normalized)) === $normalized) {
                return true;
            }
        }
        if (in_array('', $this->allowedExtensions, true) && strpos($filename, '.') === false) {
            return true;
        }

        return false;
    }
}
