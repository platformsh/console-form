<?php

namespace Platformsh\ConsoleForm\Field;

class FileField extends Field
{
    protected $requireExists = true;
    protected $requireReadable = true;
    protected $requireWritable = false;
    protected $allowedExtensions = [];

    public function __construct($name = 'File', array $config = [])
    {
        parent::__construct($name, $config);
        if (!$this->requireExists) {
            $this->requireReadable = false;
            $this->requireWritable = false;
        }

        $this->validators[] = function ($value) {
            if ($this->requireExists && !file_exists($value)) {
                return "File not found: $value";
            }
            if (is_dir($value)) {
                return "The file is a directory: $value";
            }
            if ($this->requireReadable && !is_readable($value)) {
                return "File not readable: $value";
            }
            if ($this->requireWritable && !is_writable($value)) {
                return "File not writable: $value";
            }
            if (!empty($this->allowedExtensions) && !$this->matchesAllowedExtension($value)) {
                return "Invalid file extension (allowed: " . implode(', ', $this->allowedExtensions) . ")";
            }

            return true;
        };
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
        foreach ($this->allowedExtensions as $allowedExtension) {
            if (substr($filename, - strlen($allowedExtension)) === $allowedExtension) {
                return true;
            }
        }
        if (in_array('', $this->allowedExtensions, true) && strpos($filename, '.') === false) {
            return true;
        }

        return false;
    }
}
