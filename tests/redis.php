<?php

require_once __DIR__ . '/../vendor/autoload.php';

$phpunit  = simplexml_load_file(__DIR__ . '/phpunit.xml');
$redisurl = $phpunit->xpath('/phpunit/php/const[contains(@name, "REDIS_DSN")][1]');

[$host, $port] = explode(':', (string) $redisurl[0]['value']) + [1 => 6379];

$maintainer = new \Predis\Client(['host' => $host, 'port' => $port]);
$maintainer->flushall();

$redises = [
    'phpredis' => (function () use ($host, $port) {
        $phpredis = new \Redis();
        $phpredis->connect($host, $port);
        return $phpredis;
    })(),
    'predis'   => (function () use ($host, $port) {
        return new \Predis\Client(['host' => $host, 'port' => $port]);
    })(),
];

$commands = [
    // string
    'missing set'         => ['set', 'noexists', 'hello'],
    'present set'         => ['set', 'newset', 'hello'],
    'missing setnx'       => ['setnx', 'noexists', 'hello'],
    'present setnx'       => ['setnx', 'newset', 'hello'],
    'missing append'      => ['append', 'noexists', 'world'],
    'present append'      => ['append', 'newset', 'world'],
    'missing get'         => ['get', 'noexists'],
    'present get'         => ['get', 'newset'],
    'missing strlen'      => ['strlen', 'noexists'],
    'present strlen'      => ['strlen', 'newset'],
    // hash
    'missing hmset'       => ['hmset', 'noexists', ['k' => 'v']],
    'present hmset'       => ['hmset', 'newhmset', ['k' => 'v']],
    'missing hset'        => ['hset', 'noexists', 'k', 'v'],
    'present hset'        => ['hset', 'newhset', 'k', 'v'],
    'missing hset key'    => ['hset', 'noexists', 'k2', 'v'],
    'present hset key'    => ['hset', 'newhset', 'k2', 'v'],
    'present hset key2'   => ['hset', 'newhset', 'k2', 'v2'],
    'missing hexists'     => ['hexists', 'noexists', 'N'],
    'present hexists'     => ['hexists', 'newhset', 'N'],
    'present hexists key' => ['hexists', 'newhset', 'k'],
    'missing hstrlen'     => ['hstrlen', 'noexists', 'N'],
    'present hstrlen'     => ['hstrlen', 'newhset', 'N'],
    'present hstrlen key' => ['hstrlen', 'newhset', 'k'],
    'missing hmget'       => ['hmget', 'noexists', ['k', 'N']],
    'present hmget'       => ['hmget', 'newhset', ['k', 'N']],
    'missing hgetall'     => ['hgetall', 'noexists'],
    'present hgetall'     => ['hgetall', 'newhset'],
    // all
    'missing rename'      => ['rename', 'noexists', 'noexists2'],
    'present rename'      => ['rename', 'existence', 'newrename'],
    'present rename2'     => ['rename', 'newrename', 'existence'],
    'missing exists'      => ['exists', 'noexists'],
    'present exists'      => ['exists', 'existence'],
    'missing expire'      => ['expire', 'noexists', 99],
    'present expire'      => ['expire', 'existence', 99],
    'missing del'         => ['del', 'noexists'],
    'present del'         => ['del', 'existence'],
    'missing move'        => ['move', 'noexists', 8],
    'present move'        => ['move', 'existence', 8],
];

$result = [];
foreach ($commands as $scenario => $command) {
    $cmd = array_shift($command);
    $arg = $command;

    $database = 1;
    $returns  = [];
    foreach ($redises as $name => $redis) {
        $redis->select($database++);

        // fixture
        $maintainer->select(8);
        $maintainer->flushdb();
        $redis->del('noexists');
        $redis->set('existence', 'V');

        $returns[$name] = (function ($redis, $command, ...$args) {
            try {
                $return = $redis->$command(...$args);
                if (is_object($return)) {
                    return sprintf('%s(%s)', (new ReflectionClass($return))->getShortName(), json_encode((string) $return));
                }
                return json_encode($return);
            }
            catch (Throwable $t) {
                return sprintf('%s(%s)', (new ReflectionClass($t))->getShortName(), $t->getMessage());
            }
        })($redis, $cmd, ...$arg);
    }

    $result[] = array_replace([
        'scenario' => $scenario,
        "command"  => sprintf("%s(%s)", $cmd, implode(', ', array_map(fn($v) => is_array($v) ? json_encode($v) : var_export($v, true), $arg))),
    ], $returns);
}

echo (function ($array) {
    $keys    = array_keys(reset($array));
    $lengths = [];
    array_walk_recursive($array, function ($v, $k) use (&$lengths) {
        $lengths[$k] = max($lengths[$k] ?? 3, strlen($k), strlen($v));
    });

    $linebuilder = function ($fields, $padstr) use ($lengths) {
        $line = array_map(fn($k, $v) => $v . str_repeat($padstr, $lengths[$k] - (strlen($v))), array_keys($fields), $fields);
        return '| ' . implode(' | ', $line) . ' |';
    };

    $result   = [];
    $result[] = $linebuilder(array_combine($keys, $keys), ' ');
    $result[] = $linebuilder(array_fill_keys($keys, ''), '-');
    foreach ($array as $fields) {
        $result[] = $linebuilder($fields, ' ');
    }

    return implode("\n", $result) . "\n";
})($result);
