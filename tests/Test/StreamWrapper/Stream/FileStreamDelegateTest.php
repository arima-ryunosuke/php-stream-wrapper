<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\DelegateTrait;
use ryunosuke\StreamWrapper\PhpStreamWrapperInterface;
use ryunosuke\StreamWrapper\StreamWrapperAdapterInterface;
use ryunosuke\StreamWrapper\Utils\Url;

class FileStreamDelegateTest extends FileStreamTest
{
    public static function setUpBeforeClass(): void
    {
        $STATIC = static::class;

        static::$scheme0   = "file2";
        static::$scheme1   = "file2";
        static::$namespace = sys_get_temp_dir() . "/sw2/";

        $fsd = new class implements PhpStreamWrapperInterface, StreamWrapperAdapterInterface {
            use DelegateTrait;

            protected function delegate(string $function, ...$args)
            {
                $urlargs = $this->___getUrlArgs($function);
                foreach ($args as $n => $arg) {
                    if (in_array($n, $urlargs, true)) {
                        $url         = new Url($arg, DIRECTORY_SEPARATOR === '\\');
                        $url->scheme = 'file';
                        $args[$n]    = (string) $url;
                    }
                }

                return ErrorException::handle(fn() => $function(...$args));
            }
        };
        stream_wrapper_register('file2', get_class($fsd));

        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/sub1/sub2/sub3", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}//sub1/sub2/sub3", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/this/is", 0777, true);
        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/sub2", 0777, true);

        @mkdir("{$STATIC::$scheme0}://{$STATIC::$namespace}/mkdir_r");
        touch("{$STATIC::$scheme0}://{$STATIC::$namespace}/mkdir_r/file");
    }
}
