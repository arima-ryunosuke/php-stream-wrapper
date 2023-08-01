<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\CompatibleFileSystemTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIteratorTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamOptionTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamStandardTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlPermissionTest;

class FileStreamTest extends AbstractStreamTestCase
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

        static::$scheme0   = "file";
        static::$scheme1   = "file";
        static::$namespace = sys_get_temp_dir() . "/sw/";

        @mkdir("{$STATIC::$namespace}/", 0777, true);
        @mkdir("{$STATIC::$namespace}/sub1/sub2/sub3", 0777, true);
        @mkdir("{$STATIC::$namespace}//sub1/sub2/sub3", 0777, true);
        @mkdir("{$STATIC::$namespace}/this/is", 0777, true);
        @mkdir("{$STATIC::$namespace}/sub2", 0777, true);

        @mkdir("{$STATIC::$namespace}/mkdir_r");
        touch("{$STATIC::$namespace}/mkdir_r/file");
    }
}
