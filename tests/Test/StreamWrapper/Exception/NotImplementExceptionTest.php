<?php

namespace ryunosuke\Test\StreamWrapper\Exception;

use ryunosuke\StreamWrapper\Exception\NotImplementException;
use ryunosuke\Test\StreamWrapper\Stream\AbstractStreamTestCase;

class NotImplementExceptionTest extends AbstractStreamTestCase
{
    function test_throw()
    {
        try {
            NotImplementException::throw();
        }
        catch (NotImplementException $e) {
            that($e)->getMessage()->contains(__METHOD__);
        }
    }
}
