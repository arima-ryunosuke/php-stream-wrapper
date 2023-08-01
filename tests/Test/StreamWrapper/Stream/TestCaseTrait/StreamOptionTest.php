<?php

namespace ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait;

trait StreamOptionTest
{
    function test_stream_set_blocking()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/option";

        $fp = fopen($url, 'w+');
        that(stream_set_blocking($fp, true))->is(static::$scheme0 !== 'file' || DIRECTORY_SEPARATOR === '/');
    }

    function test_stream_set_read_buffer()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/option";

        $fp = fopen($url, 'w+');
        that(stream_set_read_buffer($fp, 123))->is(0);
    }

    function test_stream_set_write_buffer()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/option";

        $fp = fopen($url, 'w+');
        that(stream_set_write_buffer($fp, 456))->is(static::$scheme0 === 'file' ? -1 : 0);
    }

    function test_stream_set_timeout()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/option";

        $fp = fopen($url, 'w+');
        that(stream_set_timeout($fp, 789))->is(static::$scheme0 !== 'file');
    }
}
