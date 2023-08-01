<?php

namespace ryunosuke\Test\StreamWrapper\Utils;

use ryunosuke\StreamWrapper\Utils\Resource;
use ryunosuke\Test\StreamWrapper\Stream\AbstractStreamTestCase;

class ResourceTest extends AbstractStreamTestCase
{
    function test_toString()
    {
        that((string) new Resource(['url' => 'URL']))->is('URL');
        that((string) new Resource(['handle' => tmpfile()]))->stringStartsWith('Resource id');
    }
}
