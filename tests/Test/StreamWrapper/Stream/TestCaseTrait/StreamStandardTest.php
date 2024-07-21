<?php

namespace ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait;

use ryunosuke\Test\StreamWrapper\Stream\FileStreamTest;

trait StreamStandardTest
{
    function test_stream_open()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/w";
        file_put_contents($url, 'abc');
        $fp = fopen($url, 'w');
        that(filesize($url))->is(0);
        fwrite($fp, 'X');
        fclose($fp);
        clearstatcache();
        that(filesize($url))->is(1);

        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/x";
        @unlink($url);
        $fp = fopen($url, 'x');
        that(filesize($url))->is(0);
        fwrite($fp, 'X');
        fclose($fp);
        clearstatcache();
        that(filesize($url))->is(1);

        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/c";
        file_put_contents($url, 'abc');
        $fp = fopen($url, 'c');
        that(filesize($url))->is(3);
        fwrite($fp, 'X');
        fclose($fp);
        clearstatcache();
        that(filesize($url))->is(3);

        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/a";
        file_put_contents($url, 'abc');
        $fp = fopen($url, 'a');
        that(filesize($url))->is(3);
        fwrite($fp, 'X');
        fclose($fp);
        clearstatcache();
        that(filesize($url))->is(4);

        unlink($url);
        $fp = fopen($url, 'a');
        that(filesize($url))->is(0);
        fwrite($fp, 'X');
        fclose($fp);
        clearstatcache();
        that(filesize($url))->is(1);

        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/z";
        @fopen($url, 'z');
        that(error_get_last()['message'])->contains('Failed to open stream', false);
    }

    function test_stream_error()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/file";
        @unlink($url);

        that(@fopen($url, 'r'))->isFalse();
        that(error_get_last()['message'])->contains('Failed to open stream', false);

        $fp = fopen($url, 'w');
        that(@fread($fp, 256))->is('');
        that(error_get_last()['message'])->contains('Bad file descriptor', false);
        fclose($fp);

        $fp = fopen($url, 'r');
        that(@fwrite($fp, 'test'))->is(0);
        that(error_get_last()['message'])->contains('Bad file descriptor', false);
        fclose($fp);

        file_put_contents($url, 'dummy');

        $fp = fopen($url, 'r');
        if (version_compare(PHP_VERSION, 8) < 0) {
            that(@ftruncate($fp, -1))->is(false);
            that(error_get_last()['message'])->contains('Negative size is not supported', false);
            that(@fread($fp, 0))->is(false);
            that(error_get_last()['message'])->contains('Length parameter must be greater than 0', false);
        }
        that(@ftruncate($fp, 1))->is(false);
        fclose($fp);
        that(file_get_contents($url))->is('dummy');

        $fp = fopen($url, 'r+');
        that(ftruncate($fp, 1))->is(true);
        fclose($fp);
        that(file_get_contents($url))->is('d');

        @fopen($url, 'x');
        that(error_get_last()['message'])->contains('Failed to open stream', false);
    }

    function test_stream_wacr_seek()
    {
        $fp = fopen(static::$scheme0 . "://" . static::$namespace . "/this/is/key", 'w+');
        that(fwrite($fp, 'test'))->is(4);
        that(@fseek($fp, -2))->is(-1);
        that(@fseek($fp, 0, 999))->is(-1);
        that(ftell($fp))->is(4);
        that(fseek($fp, 2))->is(0);
        that(fread($fp, 256))->is('st');
        that(fseek($fp, 1))->is(0);
        that(fwrite($fp, 'TEST'))->is(4);
        that(rewind($fp))->is(true);
        that(fread($fp, 256))->is('tTEST');
        that(fseek($fp, 0, SEEK_END))->is(0);
        that(fwrite($fp, 'END'))->is(3);
        that(rewind($fp))->is(true);
        that(fread($fp, 256))->is('tTESTEND');
        fclose($fp);

        $fp = fopen(static::$scheme0 . "://" . static::$namespace . "/this/is/key", 'a+');
        that(fwrite($fp, 'AAA'))->is(3);
        that(fseek($fp, 0))->is(0);
        that(fwrite($fp, 'ZZZ'))->is(3);
        fclose($fp);

        $fp = fopen(static::$scheme0 . "://" . static::$namespace . "/this/is/key", 'c+');
        that(fread($fp, 256))->is('tTESTENDAAAZZZ');
        that(ftruncate($fp, 0))->is(true);
        that(fwrite($fp, 'ZZZZ'))->is(4);
        fclose($fp);

        $fp = fopen(static::$scheme0 . "://" . static::$namespace . "/this/is/key", 'r');
        that(fread($fp, 256))->is("\0\0\0\0\0\0\0\0\0\0\0\0\0\0ZZZZ");
        fclose($fp);
    }

    function test_stream_append_rw()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/append";
        file_put_contents($url, 'initial');
        $fp = fopen($url, 'a+');
        that(fstat($fp))['size']->is(strlen('initial'));
        that(fwrite($fp, 'head'))->is(4);
        that(fstat($fp))['size']->is(strlen('initialhead'));
        that(fseek($fp, 7))->is(0);
        that(fread($fp, 256))->is('head');
        that(fwrite($fp, 'body'))->is(4);
        that(fstat($fp))['size']->is(strlen('initialheadbody'));
        that(fseek($fp, 7))->is(0);
        that(fread($fp, 256))->is('headbody');
        that(fwrite($fp, 'tail'))->is(4);
        that(fstat($fp))['size']->is(strlen('initialheadbodytail'));
        fflush($fp);
        that(fstat($fp))['size']->is(strlen('initialheadbodytail'));
        rewind($fp);
        that(feof($fp))->is(false);
        that(fread($fp, 256))->is('initialheadbodytail');
        that(feof($fp))->is(true);
        fclose($fp);
        that(file_get_contents($url))->is('initialheadbodytail');
    }

    function test_stream_append_truncate()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/append";
        file_put_contents($url, 'initial');
        $fp = fopen($url, 'a+');
        that(ftruncate($fp, 3))->is(true);
        that(fstat($fp))['size']->is(3);
        that(fwrite($fp, 'head'))->is(4);
        that(fstat($fp))['size']->is(7);
        that(ftruncate($fp, 10))->is(true);
        that(fstat($fp))['size']->is(10);
        that(fread($fp, 1))->is("\0");
        that(fwrite($fp, 'tail'))->is(4);
        fclose($fp);
        that(file_get_contents($url))->is("inihead\0\0\0tail");
    }

    function test_stream_append_flush2()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/append";
        file_put_contents($url, 'initial');
        $fp = fopen($url, 'a');
        that(fwrite($fp, 'head'))->is(4);
        that(fflush($fp))->isTrue();
        that(fwrite($fp, 'tail'))->is(4);
        that(fflush($fp))->isTrue();
        fclose($fp);
        that(file_get_contents($url))->is("initialheadtail");
    }

    function test_stream_flock()
    {
        $fp = fopen(static::$scheme0 . "://" . static::$namespace . "/this/is/locking", 'c+');

        that(flock($fp, LOCK_UN))->isTrue();
        that(flock($fp, LOCK_EX))->isTrue();
        that(fwrite($fp, 'LOCK_EX'))->is(7);
        that(flock($fp, LOCK_UN))->isTrue();
        that(rewind($fp))->is(true);
        that(flock($fp, LOCK_SH))->isTrue();
        that(fread($fp, 7))->is('LOCK_EX');
        that(flock($fp, LOCK_UN))->isTrue();
        fclose($fp);
    }

    function test_stream_flock_nb()
    {
        if ($this instanceof FileStreamTest) {
            return;
        }

        $fp = fopen(static::$scheme0 . "://" . static::$namespace . "/this/is/locking", 'c+');
        that(flock($fp, LOCK_NB | LOCK_EX))->isTrue();
    }

    function test_stream_fstat()
    {
        if (in_array(CompatibleFileSystemTest::class, class_uses($this))) {
            return;
        }
        static::waitJustSecond();

        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/fstat";
        file_put_contents($url, '');

        $now = time();
        $fp  = fopen($url, 'c+');
        that(fstat($fp))['size']->is(0);
        if (static::$supportsMetadata) {
            that(fstat($fp))['mtime']->break()->isAny([$now + 0, $now + 1]);
        }

        sleep(1);
        that(fwrite($fp, 'stat'))->is(4);
        that(fstat($fp))['size']->is(4);
        if (static::$supportsMetadata) {
            that(fstat($fp))['mtime']->break()->isAny([$now + 1, $now + 2]);
        }

        sleep(1);
        that(fwrite($fp, 'append'))->is(6);
        that(fstat($fp))['size']->is(10);
        if (static::$supportsMetadata) {
            that(fstat($fp))['mtime']->break()->isAny([$now + 2, $now + 3]);
        }
        fclose($fp);
    }

    function test_stream_filter()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/filter";
        file_put_contents("php://filter/write=string.toupper/resource=$url", 'aaa');
        that(file_get_contents($url))->is('AAA');
    }

    function test_stream_select()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/select";
        file_put_contents($url, 'aaa');
        that(mime_content_type($url))->is('text/plain');
    }
}
