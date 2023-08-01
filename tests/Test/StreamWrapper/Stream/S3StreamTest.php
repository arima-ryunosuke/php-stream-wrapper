<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Stream\S3Stream;
use ryunosuke\StreamWrapper\Utils\Url;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIteratorTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamOptionTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamStandardTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlPermissionTest;

class S3StreamTest extends AbstractStreamTestCase
{
    use DirectoryIOTest;
    use DirectoryIteratorTest;
    use UrlIOTest;
    use UrlPermissionTest;
    use StreamStandardTest;
    use StreamOptionTest;

    protected static bool $supportsDirectory = true;
    protected static bool $supportsMetadata  = true;

    public static function isPosixOrSkip(bool $rootOnly)
    {
        // supported virtual permission system
    }

    public static function setUpBeforeClass(): void
    {
        $STATIC = static::class;
        $DSN    = static::getConstOrSkip("S3_DSN");
        $BUCKET = static::getConstOrSkip("S3_BUCKET");

        static::$scheme0   = "s3";
        static::$scheme1   = "s3-$BUCKET";
        static::$namespace = "/$BUCKET";

        $url = new Url("dummy://$DSN");
        S3Stream::register("{$STATIC::$scheme0}://{$url->authority}{$url->querystring}");
        S3Stream::register("{$STATIC::$scheme1}://{$url->authority}{$STATIC::$namespace}{$url->querystring}");

        [$s3driver] = S3Stream::resolve("{$STATIC::$scheme0}:///");
        $s3driver->refreshBucket($BUCKET);

        // for directory test
        mkdir("{$STATIC::$scheme0}:///$BUCKET/mkdir_r/", 0777, true);
        touch("{$STATIC::$scheme0}:///$BUCKET/mkdir_r/file/");
    }

    function test_resolve()
    {
        $STATIC = static::class;
        $BUCKET = static::getConstOrSkip("S3_BUCKET");

        $resolved = that(S3Stream::class)::resolve("{$STATIC::$scheme0}:///");
        $resolved[1]->is('');
        $resolved[2]->is('');

        $resolved = that(S3Stream::class)::resolve("{$STATIC::$scheme0}://host:1234/extra/key");
        $resolved[1]->is('extra');
        $resolved[2]->is('key');

        $resolved = that(S3Stream::class)::resolve("{$STATIC::$scheme1}:///keyx");
        $resolved[1]->is("$BUCKET");
        $resolved[2]->is('keyx');
    }

    function test_url_rename_other()
    {
        $STATIC = static::class;
        $BUCKET = static::getConstOrSkip("S3_BUCKET");

        $url1 = "{$STATIC::$scheme0}:///$BUCKET/this/is/from";
        $url2 = "{$STATIC::$scheme0}:///$BUCKET/this/is/to?dummy=1";

        $this->from_to($url1, $url2);
    }
}
