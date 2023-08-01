<?php
namespace ryunosuke\StreamWrapper\Utils;

use LogicException;

class Mode
{
    private string $mode;

    public function __construct(string $mode)
    {
        if (trim($mode, 'rwxca+bt') !== '') {
            throw new LogicException("mode '$mode' is invalid");
        }

        $this->mode = trim($mode, 'bt');
    }

    public function __toString(): string
    {
        return $this->mode;
    }

    public function isReadMode(): bool
    {
        return strpos($this->mode, 'r') !== false;
    }

    public function isWriteMode(): bool
    {
        return strpos($this->mode, 'w') !== false;
    }

    public function isExcludeMode(): bool
    {
        return strpos($this->mode, 'x') !== false;
    }

    public function isCreateMode(): bool
    {
        return strpos($this->mode, 'c') !== false;
    }

    public function isAppendMode(): bool
    {
        return strpos($this->mode, 'a') !== false;
    }

    public function isReadable(): bool
    {
        return strpos($this->mode, 'r') !== false || strpos($this->mode, '+') !== false;
    }

    public function isWritable(): bool
    {
        return strpos($this->mode, 'r') === false || strpos($this->mode, '+') !== false;
    }

    public function isAppendable(): bool
    {
        return strpos($this->mode, 'a') !== false;
    }
}
