<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Stream\ZipStream;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIteratorTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamOptionTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamStandardTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlIOTest;

class ZipStreamTest extends AbstractStreamTestCase
{
    use DirectoryIOTest;
    use DirectoryIteratorTest;
    use UrlIOTest;
    use StreamStandardTest;
    use StreamOptionTest;

    protected static bool $supportsDirectory = true;
    protected static bool $supportsMetadata  = false;

    public static function setUpBeforeClass(): void
    {
        $STATIC = static::class;

        static::$scheme0   = "zipfs";
        static::$scheme1   = "zipfs-pass";
        static::$namespace = '/' . strtr(sys_get_temp_dir() . '/zip/test.zip', [DIRECTORY_SEPARATOR => '/']);

        ZipStream::register("{$STATIC::$scheme0}://");
        ZipStream::register("{$STATIC::$scheme1}://:pass@" . static::$namespace);

        @unlink(sys_get_temp_dir() . '/zip/test.zip');
        @mkdir(sys_get_temp_dir() . '/zip');
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/mkdir_r/", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/sub1/sub2/sub3/", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/this/is/", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/sub2/", 0777, true);

        // for directory test
        touch("{$STATIC::$scheme0}://{$STATIC::$namespace}/mkdir_r/file");
    }

    function test_resolve()
    {
        $url = static::$scheme0 . ":///path/to/zip";

        that(ZipStream::class)::resolve($url)->wasThrown("can't detect");
    }

    function test_touch()
    {
        static::waitJustSecond();

        $url = static::$scheme0 . "://" . static::$namespace . "/this/is/touch";

        that(@touch("$url/notfound/"))->is(false);

        if (version_compare(PHP_VERSION, 8.0) >= 0) {
            that(touch($url, 1234567891, 1234567892))->isTrue();
            //that(filectime($url))->break()->is(time());
            that(filemtime($url))->is(1234567891);
            //that(fileatime($url))->is(1234567892);
        }

        that(@touch("$url/notfound/"))->is(false);
    }

    function test_url_rename_other()
    {
        $STATIC = static::class;

        $temp = strtr(sys_get_temp_dir() . '/zip', [DIRECTORY_SEPARATOR => '/']);
        $url1 = "{$STATIC::$scheme0}:///$temp/test1.zip/this-is-from";
        $url2 = "{$STATIC::$scheme0}:///$temp/test2.zip/this-is-to";

        $this->from_to($url1, $url2);
    }

    function test_file()
    {
        $STATIC = static::class;

        $temp = strtr(sys_get_temp_dir() . '/zip/invalid.zip', [DIRECTORY_SEPARATOR => '/']);
        $url  = "{$STATIC::$scheme0}:///$temp";

        file_put_contents($temp, 'invalid');
        that(@file_get_contents("$url/test.txt", 'test'))->is(false);

        unlink($temp);
        ZipStream::resolve($url)[0]->close();

        that(file_put_contents("$url/test.txt", 'test'))->is(4);
        that(file_get_contents("$url/test.txt"))->is("test");
        ZipStream::resolve($url)[0]->close();

        that(filesize($temp))->gt(100);
    }

    function test_pass()
    {
        $STATIC = static::class;

        $temp = strtr(sys_get_temp_dir() . '/zip/pass.zip', [DIRECTORY_SEPARATOR => '/']);
        copy(__DIR__ . '/../../../files/test.zip', $temp);
        $url = "{$STATIC::$scheme1}:///$temp";

        that(file_get_contents("$url/test.txt"))->is("test\n");
        file_put_contents("$url/test2.txt", 'hoge');
        that(file_get_contents("$url/test2.txt"))->is("hoge");
    }

    function test_fragmentMode()
    {
        $url = static::$scheme0 . "://" . static::$namespace;
        that(filetype("$url#this/is/"))->is('dir');
        that(mkdir("$url#this/is/dir"))->isTrue();
        that(filetype("$url#this/is/dir/"))->is('dir');
        that(touch("$url#this/is/dir/fragment1"))->isTrue();
        that(filetype("$url#this/is/dir/fragment1"))->is('file');
        that(mkdir("$url#this/is/dir/fragment2"))->isTrue();
        that(filetype("$url#this/is/dir/fragment2"))->is('dir');
        that(scandir("$url#this/is/dir"))->is(['fragment1', 'fragment2']);
    }
}
