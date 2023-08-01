<?php
namespace ryunosuke\StreamWrapper\Stream;

use Closure;
use Predis\Client;
use Predis\PredisException;
use Redis;
use RedisException;
use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\DirectoryIteratorTrait;
use ryunosuke\StreamWrapper\Mixin\StreamTrait;
use ryunosuke\StreamWrapper\Mixin\UrlIOTrait;
use ryunosuke\StreamWrapper\Mixin\UrlPermissionTrait;
use ryunosuke\StreamWrapper\Utils\Url;

class RedisStream extends AbstractStream
{
    use DirectoryIteratorTrait;
    use UrlIOTrait;
    use UrlPermissionTrait;
    use StreamTrait;

    protected static array $drivers = [];

    /** @return array{RedisDriver, string, string} */
    public static function resolve(string $url): array
    {
        $local   = new Url($url);
        $default = static::$default[$local->scheme];
        $merged  = $local->merge($default);

        $driver = static::$drivers[$merged->dsn] ??= (function () use ($merged) {
            $class = $merged->query['class'] ?? null;
            $class ??= class_exists(Redis::class) ? 'phpredis' : null;
            $class ??= class_exists(Client::class) ? 'predis' : null;

            return new RedisDriver($class, $merged->query['meta-prefix'] ?? '', [
                'host' => $merged->host,
                'port' => $merged->port,
            ]);
        })();

        $pathes   = preg_split('#/#', $default->path . $local->path, -1, PREG_SPLIT_NO_EMPTY);
        $database = array_shift($pathes);
        $key      = implode('/', $pathes);

        return [$driver, $database, $key];
    }

    protected function children(string $url, array $contextOptions): iterable
    {
        [$driver, $database, $key] = static::resolve($url);

        $keys = $driver->execute($database, 'keys', [ltrim("$key/", '/') . "*"], fn($result) => count($result));

        return array_map(fn($v) => "$url/$v", $this->iterableFilterLocalPath($key, $keys));
    }

    protected function move(string $src_url, string $dst_url, array $contextOptions): void
    {
        [$src_driver, $src_database, $src_key] = static::resolve($src_url);
        [$dst_driver, $dst_database, $dst_key] = static::resolve($dst_url);

        if ($src_driver !== $dst_driver) {
            $this->intermove($src_url, $dst_url, $contextOptions);
            return;
        }

        if ($src_database === $dst_database) {
            $src_driver->executeWithMeta($src_database, 'rename', [$src_key, $dst_key], [0, 1], fn($result) => $result !== false);
        }
        else {
            $src_driver->executeWithMeta($dst_database, 'del', [$src_key], [0], null);
            $src_driver->executeWithMeta($src_database, 'move', [$src_key, $dst_database], [0], fn($result) => $result !== false);
            $src_driver->executeWithMeta($dst_database, 'rename', [$src_key, $dst_key], [0, 1], fn($result) => $result !== false);
        }
    }

    protected function getMetadata(string $url): ?array
    {
        [$driver, $database, $key] = static::resolve($url);

        $size = $driver->execute($database, 'strlen', [$key], fn($result) => is_int($result));
        if ($size === 0 && !$driver->execute($database, 'exists', [$key], fn($result) => $result !== null)) {
            return null;
        }

        $metadata = [];
        if (strlen($driver->metaPrefix)) {
            $metadata = $driver->execute($database, 'hgetall', ["{$driver->metaPrefix}$key"], fn($result) => !empty($result));
        }
        return array_replace($metadata, ['size' => $size]);
    }

    protected function setMetadata(string $url, array $metadata): void
    {
        [$driver, $database, $key] = static::resolve($url);

        if (strlen($driver->metaPrefix)) {
            if (!$driver->execute($database, 'exists', [$key], fn($result) => $result !== null)) {
                ErrorException::throwWarning("$url: is not found");
            }
            $driver->execute($database, 'hmset', ["{$driver->metaPrefix}$key", $metadata], fn($result) => !empty($result));
        }
    }

    protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string
    {
        [$driver, $database, $key] = static::resolve($url);

        $value = $driver->execute($database, 'get', [$key], fn($result) => !empty($result) || is_string($result));

        $metadata = [];
        if (strlen($driver->metaPrefix)) {
            $metadata = $driver->execute($database, 'hgetall', ["{$driver->metaPrefix}$key"], fn($result) => !empty($result));
        }
        $metadata = array_replace($metadata, ['size' => strlen($value)]);

        return $value;
    }

    protected function createFile(string $url, string $value, array $metadata, array $contextOptions): void
    {
        [$driver, $database, $key] = static::resolve($url);

        $driver->execute($database, 'set', [$key, $value], fn($result) => $result !== null);
        if (strlen($driver->metaPrefix)) {
            $driver->execute($database, 'hmset', ["{$driver->metaPrefix}$key", $metadata], fn($result) => !empty($result));
        }

        if (($ttl = $contextOptions['ttl'] ?? null) !== null) {
            $driver->executeWithMeta($database, 'expire', [$key, $ttl], [0], fn($result) => !empty($result));
        }
        elseif (($expire = $contextOptions['expire'] ?? null) !== null) {
            $driver->executeWithMeta($database, 'expireat', [$key, $expire], [0], fn($result) => !empty($result));
        }
    }

    protected function appendFile(string $url, string $contents, array $contextOptions): void
    {
        [$driver, $database, $key] = static::resolve($url);

        $driver->execute($database, 'append', [$key, $contents], fn($result) => is_int($result));
    }

    protected function deleteFile(string $url, array $contextOptions): void
    {
        [$driver, $database, $key] = static::resolve($url);

        $driver->executeWithMeta($database, 'del', [$key], [0], fn($result) => $result !== 0);
    }
}

/**
 * | scenario            | command                          | phpredis              | predis                           |
 * | ------------------- | -------------------------------- | --------------------- | -------------------------------- |
 * | missing set         | set('noexists', 'hello')         | true                  | Status("OK")                     |
 * | present set         | set('newset', 'hello')           | true                  | Status("OK")                     |
 * | missing setnx       | setnx('noexists', 'hello')       | true                  | 1                                |
 * | present setnx       | setnx('newset', 'hello')         | false                 | 0                                |
 * | missing append      | append('noexists', 'world')      | 5                     | 5                                |
 * | present append      | append('newset', 'world')        | 10                    | 10                               |
 * | missing get         | get('noexists')                  | false                 | null                             |
 * | present get         | get('newset')                    | "helloworld"          | "helloworld"                     |
 * | missing strlen      | strlen('noexists')               | 0                     | 0                                |
 * | present strlen      | strlen('newset')                 | 10                    | 10                               |
 * | missing hmset       | hmset('noexists', {"k":"v"})     | true                  | Status("OK")                     |
 * | present hmset       | hmset('newhmset', {"k":"v"})     | true                  | Status("OK")                     |
 * | missing hset        | hset('noexists', 'k', 'v')       | 1                     | 1                                |
 * | present hset        | hset('newhset', 'k', 'v')        | 1                     | 1                                |
 * | missing hset key    | hset('noexists', 'k2', 'v')      | 1                     | 1                                |
 * | present hset key    | hset('newhset', 'k2', 'v')       | 1                     | 1                                |
 * | present hset key2   | hset('newhset', 'k2', 'v2')      | 0                     | 0                                |
 * | missing hexists     | hexists('noexists', 'N')         | false                 | 0                                |
 * | present hexists     | hexists('newhset', 'N')          | false                 | 0                                |
 * | present hexists key | hexists('newhset', 'k')          | true                  | 1                                |
 * | missing hstrlen     | hstrlen('noexists', 'N')         | 0                     | 0                                |
 * | present hstrlen     | hstrlen('newhset', 'N')          | 0                     | 0                                |
 * | present hstrlen key | hstrlen('newhset', 'k')          | 1                     | 1                                |
 * | missing hmget       | hmget('noexists', ["k","N"])     | {"k":false,"N":false} | [null,null]                      |
 * | present hmget       | hmget('newhset', ["k","N"])      | {"k":"v","N":false}   | ["v",null]                       |
 * | missing hgetall     | hgetall('noexists')              | []                    | []                               |
 * | present hgetall     | hgetall('newhset')               | {"k":"v","k2":"v2"}   | {"k":"v","k2":"v2"}              |
 * | missing rename      | rename('noexists', 'noexists2')  | false                 | ServerException(ERR no such key) |
 * | present rename      | rename('existence', 'newrename') | true                  | Status("OK")                     |
 * | present rename2     | rename('newrename', 'existence') | true                  | Status("OK")                     |
 * | missing exists      | exists('noexists')               | 0                     | 0                                |
 * | present exists      | exists('existence')              | 1                     | 1                                |
 * | missing expire      | expire('noexists', 99)           | false                 | 0                                |
 * | present expire      | expire('existence', 99)          | true                  | 1                                |
 * | missing del         | del('noexists')                  | 0                     | 0                                |
 * | present del         | del('existence')                 | 1                     | 1                                |
 * | missing move        | move('noexists', 8)              | false                 | 0                                |
 * | present move        | move('existence', 8)             | true                  | 1                                |
 */
class RedisDriver
{
    /** @var Redis|Client */
    private       $client;
    private array $parameters;
    public string $metaPrefix;

    private int $database;

    public function __construct(string $class, string $metaPrefix, array $parameters)
    {
        $this->metaPrefix = $metaPrefix;
        $this->parameters = $parameters;
        $this->client     = $class === 'phpredis' ? new Redis() : new Client($this->parameters);
    }

    public function select($database)
    {
        if (!$this->client->isConnected()) {
            $this->client->connect($this->parameters['host'], $this->parameters['port']);
        }

        if (ctype_digit("$database")) {
            $database = (int) $database;
            if (($this->database ?? null) !== $database) {
                $this->database = $database;
                $this->client->select($database);
            }
        }

        return $this->client;
    }

    public function execute($database, string $command, array $arguments, Closure $expected = null)
    {
        try {
            $result = $this->select($database)->$command(...$arguments);
            if ($expected !== null) {
                if (!$expected($result)) {
                    ErrorException::throwWarning(sprintf('%s(%s) no expected return. actual is %s', $command, json_encode($arguments), json_encode($result)));
                }
            }
            return $result;
        }
        catch (RedisException|PredisException $e) {
            ErrorException::throwWarning($e->getMessage(), $e); // @codeCoverageIgnore
        }
    }

    public function executeWithMeta($database, string $command, array $arguments, array $withIndexes = [], Closure $expected = null)
    {
        $this->execute($database, $command, $arguments, $expected);
        if (strlen($this->metaPrefix)) {
            foreach ($withIndexes as $index) {
                $arguments[$index] = "{$this->metaPrefix}{$arguments[$index]}";
            }
            $this->execute($database, $command, $arguments, $expected);
        }
    }
}
