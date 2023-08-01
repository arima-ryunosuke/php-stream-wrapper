<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Stream\AbstractStream;
use ryunosuke\StreamWrapper\StreamWrapperAdapterTrait;
use ryunosuke\StreamWrapper\StreamWrapperNoopTrait;

class AbstractStreamTest extends AbstractStreamTestCase
{
    function test_flags_override()
    {
        that(AbstractStream::class)::register('hoge://', ['override' => null, 'url' => true])->isTrue();
        that(stream_is_local('hoge://'))->isFalse();

        that(AbstractStream::class)::register('hoge://', ['override' => null, 'url' => false])->isFalse();
        that(stream_is_local('hoge://'))->isFalse();

        that(AbstractStream::class)::register('hoge://', ['override' => true, 'url' => false])->isTrue();
        that(stream_is_local('hoge://'))->isTrue();

        that(AbstractStream::class)::register('hoge://', ['override' => false])->wasThrown('already');
    }

    public function test_context()
    {
        MockStream::register('mock://localhost', [], [
            'hoge' => 'Hoge',
        ]);

        mkdir('mock://test');
        that($GLOBALS['options'])->is([
            'hoge' => 'Hoge',
        ]);
        that($GLOBALS['params'])->is([]);

        mkdir('mock://test', 0777, true, stream_context_create([
            'mock' => [
                'hoge' => 'Foo',
                'fuga' => 'Fuga',
            ],
        ], [
            'notification' => 'callback',
        ]));
        that($GLOBALS['options'])->is([
            'hoge' => 'Foo',
            'fuga' => 'Fuga',
        ]);
        that($GLOBALS['params'])->is([
            'notification' => 'callback',
        ]);
    }
}

class MockStream extends AbstractStream
{
    use StreamWrapperAdapterTrait;
    use StreamWrapperNoopTrait;

    public function _mkdir(string $url, int $permissions = 0777, bool $recursive = false, array $options = [], array $params = []): bool
    {
        $GLOBALS['options'] = $options;
        $GLOBALS['params']  = $params;
        return true;
    }
}
