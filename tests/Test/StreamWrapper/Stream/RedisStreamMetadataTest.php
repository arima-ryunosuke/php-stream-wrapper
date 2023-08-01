<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use Predis\Client;
use ryunosuke\StreamWrapper\Stream\RedisStream;
use ryunosuke\StreamWrapper\Utils\Url;
use ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait\UrlPermissionTest;

class RedisStreamMetadataTest extends RedisStreamTest
{
    use UrlPermissionTest;

    protected static bool $supportsDirectory = false;
    protected static bool $supportsMetadata  = true;

    public static function setUpBeforeClass(): void
    {
        $STATIC = static::class;
        $DSN    = static::getConstOrSkip("REDIS_DSN");

        static::$scheme0   = "redis-m";
        static::$scheme1   = "redis-m-1";
        static::$namespace = "/1";

        $url = new Url("dummy://$DSN");
        $url = $url->merge(new Url('dummy://?meta-prefix=meta@&class=predis'));
        RedisStream::register("{$STATIC::$scheme0}://{$url->authority}{$url->querystring}");
        RedisStream::register("{$STATIC::$scheme1}://{$url->authority}{$STATIC::$namespace}{$url->querystring}");

        $redis = new Client([
            'host' => $url->host,
            'port' => $url->port,
        ]);
        $redis->flushall();
    }
}
