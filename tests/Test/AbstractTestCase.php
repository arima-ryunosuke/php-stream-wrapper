<?php
namespace ryunosuke\Test;

use ryunosuke\PHPUnit\TestCaseTrait;

abstract class AbstractTestCase extends \PHPUnit\Framework\TestCase
{
    use TestCaseTrait;

    public static function isPosixOrSkip(bool $rootOnly)
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('this test posix only');
        }

        if ($rootOnly && posix_getuid() !== 0) {
            self::markTestSkipped('this test root only');
        }
    }

    public static function isRoot()
    {
        return DIRECTORY_SEPARATOR === '/' && posix_getuid() === 0;
    }

    public static function waitJustSecond()
    {
        while (explode(' ', microtime())[0] > 0.050) {
            usleep(10 * 1000);
        }
    }
}
