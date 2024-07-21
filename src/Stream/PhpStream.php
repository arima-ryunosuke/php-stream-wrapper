<?php
namespace ryunosuke\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\DelegateTrait;
use ryunosuke\StreamWrapper\PhpStreamWrapperInterface;
use ryunosuke\StreamWrapper\StreamWrapperAdapterInterface;
use ryunosuke\StreamWrapper\Utils\Url;

class PhpStream implements PhpStreamWrapperInterface, StreamWrapperAdapterInterface
{
    use DelegateTrait;

    public static function override(): bool
    {
        stream_wrapper_unregister('php');
        return stream_wrapper_register('php', static::class);
    }

    protected function delegate(string $function, ...$args)
    {
        $urlargs = $this->___getUrlArgs($function);
        foreach ($args as $n => $arg) {
            if (in_array($n, $urlargs, true)) {
                if ($function === 'fopen') {
                    $url = new Url($arg, true);
                    $url->user = null;
                    $url->pass = null;
                    $url->host = null;
                    $url->port = null;
                    $url->query = null;
                    $url->fragment = null;
                    // php://filter contains "resource=/path/to/file.ext"
                    if (strpos($url->path, '/filter') !== 0) {
                        $url->extension = null;
                    }
                    $args[$n] = (string) $url;
                }
            }
        }

        stream_wrapper_restore('php');

        $result = ErrorException::handle(fn() => $function(...$args));

        stream_wrapper_unregister('php');
        stream_wrapper_register('php', static::class);

        return $result;
    }
}
