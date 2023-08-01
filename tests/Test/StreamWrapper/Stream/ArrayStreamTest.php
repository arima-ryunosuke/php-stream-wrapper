<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Stream\ArrayStream;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\CompatibleFileSystemTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIteratorTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamOptionTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamStandardTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlPermissionTest;

class ArrayStreamTest extends AbstractStreamTestCase
{
    use CompatibleFileSystemTest;
    use DirectoryIOTest;
    use DirectoryIteratorTest;
    use UrlIOTest;
    use UrlPermissionTest;
    use StreamStandardTest;
    use StreamOptionTest;

    protected static bool $supportsDirectory = true;
    protected static bool $supportsMetadata  = true;

    public static function setUpBeforeClass(): void
    {
        $STATIC = static::class;

        static::$scheme0   = "array";
        static::$scheme1   = "array";
        static::$namespace = '/';

        ArrayStream::register("{$STATIC::$scheme0}://");

        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}sub1/sub2/sub3", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/sub1/sub2/sub3", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}this/is", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}sub2", 0777, true);

        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}mkdir_r");
        touch("{$STATIC::$scheme0}://{$STATIC::$namespace}mkdir_r/file");
    }
}
