<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use PDO;
use ryunosuke\StreamWrapper\Stream\MysqlStream;
use ryunosuke\StreamWrapper\Utils\Url;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIteratorTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamOptionTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamStandardTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlIOTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlPermissionTest;

class MysqlStreamTest extends AbstractStreamTestCase
{
    use DirectoryIteratorTest;
    use UrlIOTest;
    use UrlPermissionTest;
    use StreamStandardTest;
    use StreamOptionTest;

    protected static bool $supportsDirectory = false;
    protected static bool $supportsMetadata  = true;

    public static function isPosixOrSkip(bool $rootOnly)
    {
        // supported virtual permission system
    }

    public static function setUpBeforeClass(): void
    {
        $STATIC = static::class;
        $DSN    = static::getConstOrSkip("MYSQL_DSN");
        $DB     = static::getConstOrSkip("MYSQL_DB");
        $TABLE1 = static::getConstOrSkip("MYSQL_TABLE1");
        $TABLE2 = static::getConstOrSkip("MYSQL_TABLE2");

        static::$scheme0   = "mysql";
        static::$scheme1   = "mysql-$DB";
        static::$namespace = "/$DB/$TABLE1";

        $url = new Url("dummy://$DSN");
        MysqlStream::register("{$STATIC::$scheme0}://{$url->authority}{$url->querystring}");
        MysqlStream::register("{$STATIC::$scheme1}://{$url->authority}/$DB/$TABLE1{$url->querystring}");

        $pdo = new PDO(
            "mysql:host={$url->host};port={$url->port}",
            $url->user,
            $url->pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        );
        $pdo->exec("
            CREATE DATABASE IF NOT EXISTS `$DB`;
            DROP TABLE IF EXISTS `$DB`.`$TABLE1`;
            DROP TABLE IF EXISTS `$DB`.`$TABLE2`;
        ");
    }

    function test_resolve()
    {
        $STATIC = static::class;
        $DB     = static::getConstOrSkip("MYSQL_DB");
        $TABLE1 = static::getConstOrSkip("MYSQL_TABLE1");

        $resolved = that(MysqlStream::class)::resolve("{$STATIC::$scheme0}:///", true);
        $resolved[1]->is('.');
        $resolved[2]->is('');

        $resolved = that(MysqlStream::class)::resolve("{$STATIC::$scheme0}://user:pass@host:1234/db/table/key", true);
        $resolved[1]->is('db.table');
        $resolved[2]->is('key');

        $resolved = that(MysqlStream::class)::resolve("{$STATIC::$scheme1}:///keyx", true);
        $resolved[1]->is("$DB.$TABLE1");
        $resolved[2]->is('keyx');
    }

    function test_url_rename_other()
    {
        $STATIC = static::class;
        $DB     = static::getConstOrSkip("MYSQL_DB");
        $TABLE1 = static::getConstOrSkip("MYSQL_TABLE1");
        $TABLE2 = static::getConstOrSkip("MYSQL_TABLE2");

        $url1 = "{$STATIC::$scheme0}:///$DB/$TABLE1/this/is/from";
        $url2 = "{$STATIC::$scheme0}:///$DB/$TABLE2/this/is/to";

        $this->from_to($url1, $url2);

        $url1 = "{$STATIC::$scheme0}:///$DB/$TABLE1/this/is/from";
        $url2 = "{$STATIC::$scheme0}:///$DB/$TABLE2/this/is/to?dummy=1";

        $this->from_to($url1, $url2);
    }
}
