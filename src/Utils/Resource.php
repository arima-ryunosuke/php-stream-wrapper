<?php
namespace ryunosuke\StreamWrapper\Utils;

use Iterator;
use stdClass;

#[\AllowDynamicProperties]
class Resource extends stdClass
{
    public string $url;

    /** @var \resource */
    public $handle;

    public Iterator $iterator;

    public Mode   $mode;
    public Stat   $stat;
    public array  $options;
    public int    $position;
    public string $contents;
    public string $appendix;
    public bool   $flushed;
    public int    $locked;
    public bool   $blocking;
    public int    $readSize;
    public int    $writeSize;
    public float  $timeout;

    public function __construct(array $attrs)
    {
        foreach ($attrs as $name => $attr) {
            $this->$name = $attr;
        }
    }

    public function __toString()
    {
        return (string) ($this->url ?? $this->handle ?? 'unknown resource');
    }
}
