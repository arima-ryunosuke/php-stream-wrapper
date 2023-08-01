<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use Predis\Client;
use ryunosuke\StreamWrapper\Stream\RedisStream;
use ryunosuke\StreamWrapper\Utils\Url;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\DirectoryIteratorTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamOptionTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\StreamStandardTest;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlIOTest;

class RedisStreamTest extends AbstractStreamTestCase
{
    use DirectoryIteratorTest;
    use UrlIOTest;
    use StreamStandardTest;
    use StreamOptionTest;

    protected static bool $supportsDirectory = false;
    protected static bool $supportsMetadata  = false;

    public static function isPosixOrSkip(bool $rootOnly)
    {
        // supported virtual permission system
    }

    public static function setUpBeforeClass(): void
    {
        $STATIC = static::class;
        $DSN    = static::getConstOrSkip("REDIS_DSN");
        $DB     = static::getConstOrSkip("REDIS_DB");

        static::$scheme0   = "redis";
        static::$scheme1   = "redis-1";
        static::$namespace = "/$DB";

        $url = new Url("dummy://$DSN");
        RedisStream::register("{$STATIC::$scheme0}://{$url->authority}{$url->querystring}");
        RedisStream::register("{$STATIC::$scheme1}://{$url->authority}{$STATIC::$namespace}{$url->querystring}");

        $redis = new Client([
            'host' => $url->host,
            'port' => $url->port,
        ]);
        $redis->flushall();
    }

    function test_resolve()
    {
        $STATIC = static::class;
        $DB     = static::getConstOrSkip("REDIS_DB");

        $resolved = that(RedisStream::class)::resolve("{$STATIC::$scheme0}:///");
        $resolved[1]->is('');
        $resolved[2]->is('');

        $resolved = that(RedisStream::class)::resolve("{$STATIC::$scheme0}://host:1234/3/key");
        $resolved[1]->is('3');
        $resolved[2]->is('key');

        $resolved = that(RedisStream::class)::resolve("{$STATIC::$scheme1}:///keyx");
        $resolved[1]->is($DB);
        $resolved[2]->is('keyx');
    }

    function test_ttl()
    {
        $url0 = static::$scheme0 . "://" . static::$namespace . "/this/is/ttl";
        $url1 = static::$scheme0 . "://" . static::$namespace . "/this/is/ttl1";
        $url2 = static::$scheme0 . "://" . static::$namespace . "/this/is/expire1";
        $url3 = static::$scheme0 . "://" . static::$namespace . "/this/is/ttl5";
        $url4 = static::$scheme0 . "://" . static::$namespace . "/this/is/expire5";

        file_put_contents($url0, 'no');
        file_put_contents($url1, 't1', 0, stream_context_create([
            static::$scheme0 => [
                'ttl' => 1,
            ],
        ]));
        file_put_contents($url2, 'e1', 0, stream_context_create([
            static::$scheme0 => [
                'expire' => time() + 1,
            ],
        ]));
        file_put_contents($url3, 't5', 0, stream_context_create([
            static::$scheme0 => [
                'ttl' => 5,
            ],
        ]));
        file_put_contents($url4, 'e5', 0, stream_context_create([
            static::$scheme0 => [
                'expire' => time() + 5,
            ],
        ]));

        sleep(1);
        that(file_exists($url0))->isTrue();
        that(file_exists($url1))->isFalse();
        that(file_exists($url2))->isFalse();
        that(file_exists($url3))->isTrue();
        that(file_exists($url4))->isTrue();
    }

    function test_url_rename_other()
    {
        $STATIC = static::class;
        $DSN    = static::getConstOrSkip("REDIS_DSN");

        $url1 = "{$STATIC::$scheme0}:///1/this/is/from";
        $url2 = "{$STATIC::$scheme0}:///2/this/is/to";

        $this->from_to($url1, $url2);

        $url1 = "{$STATIC::$scheme0}://$DSN/1#this/is/from";
        $url2 = "{$STATIC::$scheme0}://$DSN/2/this/is/to?dummy=1";

        $this->from_to($url1, $url2);
    }
}
