<?php

namespace ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait;

trait CompatibleFileSystemTest
{
    function test_nodir()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/nodir";
        that(@touch("$url/file"))->isFalse();
        that(@rmdir("$url"))->isFalse();
    }

    function test_read_dir()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/read_dir";
        @mkdir($url);
        that(@file_get_contents($url))->is(false);
        that(error_get_last()['message'])->contains('failed', false);
    }

    function test_unlink_dir()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/unlink_dir";
        @mkdir($url);
        that(@unlink($url))->isFalse();
        that(error_get_last()['message'])->contains('directory');
    }

    function test_rmdir_file()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/rmdir_file";
        @touch($url);
        that(@rmdir($url))->isFalse();
        that(error_get_last()['message'])->contains('directory');
    }

    function test_rename_dir()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/nesting";
        @unlink("$url/dir_dst/file");
        @rmdir("$url/dir_dst");
        @mkdir("$url/dir_src", 0777, true);
        touch("$url/dir_src/file");

        that(rename("$url/dir_src", "$url/dir_dst"))->isTrue();
        that(file_exists("$url/dir_src"))->isFalse();
        that(file_exists("$url/dir_dst"))->isTrue();
        that(file_exists("$url/dir_dst/file"))->isTrue();
    }
}
