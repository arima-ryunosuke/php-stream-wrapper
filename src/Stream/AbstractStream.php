<?php
namespace ryunosuke\StreamWrapper\Stream;

use LogicException;
use ryunosuke\StreamWrapper\PhpStreamWrapperInterface;
use ryunosuke\StreamWrapper\StreamWrapperAdapterInterface;
use ryunosuke\StreamWrapper\StreamWrapperAdapterTrait;
use ryunosuke\StreamWrapper\StreamWrapperNoopTrait;
use ryunosuke\StreamWrapper\Utils\Url;

abstract class AbstractStream implements PhpStreamWrapperInterface, StreamWrapperAdapterInterface
{
    use StreamWrapperAdapterTrait;
    use StreamWrapperNoopTrait;

    /** @var Url[] */
    protected static array $default;

    public static function register(string $defaultUrl, array $flags = [], array $contextOptions = []): bool
    {
        $url = new Url($defaultUrl);

        $flags += [
            'override' => false,
            'url'      => false,
        ];

        if (in_array($url->scheme, stream_get_wrappers(), true)) {
            if ($flags['override'] === null) {
                return false;
            }
            if ($flags['override'] === false) {
                throw new LogicException("{$url->scheme} is already registered wrapper");
            }
            if ($flags['override'] === true) {
                stream_wrapper_unregister($url->scheme);
            }
        }

        static::$default[$url->scheme] = $url;

        stream_context_set_default([
            $url->scheme => $contextOptions,
        ]);
        return stream_wrapper_register($url->scheme, static::class, $flags['url'] ? STREAM_IS_URL : 0);
    }
}
