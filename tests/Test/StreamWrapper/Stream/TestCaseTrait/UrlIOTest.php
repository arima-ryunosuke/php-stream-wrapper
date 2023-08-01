<?php

namespace ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait;

trait UrlIOTest
{
    function test_url_io()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/key";

        @unlink($url);
        that(@unlink($url))->isFalse();
        that(file_exists($url))->isFalse();
        that(is_file($url))->isFalse();
        that(@filesize($url))->isFalse();
        that(@file_get_contents($url))->isFalse();
        that(touch($url))->isTrue();
        that(file_exists($url))->isTrue();
        that(is_file($url))->isTrue();
        that(filesize($url))->is(0);

        that(file_put_contents($url, 'hoge'))->is(4);
        that(file_put_contents($url, 'hogera', FILE_APPEND))->is(6);
        that(file_get_contents($url))->is('hogehogera');

        that(touch($url))->isTrue();
        that(filesize($url))->is(10);

        that(unlink($url))->isTrue();
        that(file_exists($url))->isFalse();
        that(is_file($url))->isFalse();
        that(@filesize($url))->isFalse();
    }

    function test_touch()
    {
        static::waitJustSecond();

        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/touch";

        that(touch($url, 1234567891, 1234567892))->isTrue();
        that(file_exists($url))->is(true);
        that(filemtime($url))->isAny([0, 1234567891, time()]); // 0,time() for not supported
        that(fileatime($url))->isAny([0, 1234567892, time()]); // 0,time() for not supported
        if (static::$supportsMetadata) {
            that(filectime($url))->break()->is(DIRECTORY_SEPARATOR === '/' ? time() : filectime($url));
        }
    }

    function test_url_rename_copy()
    {
        $url1 = static::$scheme0 . "://" . static::$namespace . "/this/is/from";
        $url2 = static::$scheme0 . "://" . static::$namespace . "/this/is/to";

        $this->from_to($url1, $url2);
    }

    function from_to($url1, $url2)
    {
        file_put_contents($url1, 'hoge');
        @unlink($url2);

        // rename: missing -> exists
        that(@rename($url2, $url1))->isFalse();
        that(file_exists($url2))->isFalse();
        that(file_exists($url1))->isTrue();

        // rename: exists -> missing
        that(rename($url1, $url2))->isTrue();
        that(file_exists($url1))->isFalse();
        that(file_get_contents($url2))->is('hoge');

        // copy: exists -> missing
        that(copy($url2, $url1))->isTrue();
        that(file_get_contents($url2))->is('hoge');
        that(file_get_contents($url1))->is('hoge');

        // rename: exists -> exists
        that(rename($url1, $url2))->isTrue();
        that(file_get_contents($url2))->is('hoge');
        that(file_exists($url1))->isFalse();
    }

    function test_stat()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/key";

        file_put_contents($url, 'hoge');

        that(stat($url))[7]->is(4);
        that(stat($url))['size']->is(4);
    }

    function test_lstat()
    {
        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/key";

        file_put_contents($url, 'hoge');

        that(lstat($url))[7]->is(4);
        that(lstat($url))['size']->is(4);
    }
}
