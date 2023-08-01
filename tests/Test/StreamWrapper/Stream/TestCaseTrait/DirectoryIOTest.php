<?php

namespace ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait;

trait DirectoryIOTest
{
    function test_stat_top()
    {
        that(is_dir(static::$scheme0 . "://" . static::$namespace))->isTrue();
    }

    function test_mkdir()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/mkdir";

        @rmdir("$url/sub1/sub2");
        @rmdir("$url/sub1");
        @rmdir($url);

        that(mkdir($url))->isTrue();
        that(@mkdir($url))->isFalse();
        that(@mkdir("$url/sub1/sub2"))->isFalse();
        that(mkdir("$url/sub1"))->isTrue();
        that(mkdir("$url/sub1/sub2"))->isTrue();
        that(filetype("$url/"))->is('dir');
        that(filetype("$url/sub1/"))->is('dir');
        that(filetype("$url/sub1/sub2/"))->is('dir');
    }

    function test_mkdir_r()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/mkdir_r";

        @rmdir("$url/sub1/sub2/sub3");
        @mkdir("$url/sub1/sub2", 0777, true);

        that(mkdir("$url/sub1/sub2/sub3", 0777, true))->isTrue();
        that(@mkdir("$url/sub1/sub2/sub3", 0777, true))->isFalse();
        that(@mkdir("$url/file/dir", 0777, true))->isFalse();
    }

    function test_rmdir()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/rmdir";

        @mkdir("$url/sub1/sub2", 0777, true);
        that(@rmdir($url))->isFalse();
        that(rmdir("$url/sub1/sub2"))->isTrue();
        that(rmdir("$url/sub1"))->isTrue();
        that(rmdir("$url"))->isTrue();
        that(@rmdir("$url"))->isFalse();
    }

    function tessst_renamedir()
    {
        $url1 = static::$scheme0 . "://" . static::$namespace . "/rename_src/";
        $url2 = static::$scheme0 . "://" . static::$namespace . "/rename_dst/";

        @mkdir($url1);
        @rmdir($url2);
        that(rename($url1, $url2))->isTrue();
        that(is_dir($url2))->isTrue();
        that(@rename($url1, $url2))->isFalse();
    }
}
