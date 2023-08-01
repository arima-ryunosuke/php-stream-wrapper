<?php
namespace ryunosuke\StreamWrapper;

use ryunosuke\StreamWrapper\Exception\ErrorException;

/**
 * Adapter trait this package to php stream wrapper
 */
trait StreamWrapperAdapterTrait
{
    private object $___resource;

    public $context;

    public function mkdir(string $path, int $mode, int $options): bool
    {
        try {
            clearstatcache(true, $path);
            return $this->_mkdir($path, $mode, !!($options & STREAM_MKDIR_RECURSIVE), ...$this->___getContextParamsByUrl($path));
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function rmdir(string $path, int $options): bool
    {
        // never use currently
        assert($options === 8);

        try {
            clearstatcache(true, $path);
            return $this->_rmdir($path, ...$this->___getContextParamsByUrl($path));
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function rename(string $path_from, string $path_to): bool
    {
        try {
            clearstatcache(true, $path_from);
            clearstatcache(true, $path_to);

            return $this->_rename($path_from, $path_to, ...$this->___getContextParamsByUrl($path_from));
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function unlink(string $path): bool
    {
        try {
            clearstatcache(true, $path);
            return $this->_unlink($path, ...$this->___getContextParamsByUrl($path));
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function url_stat(string $path, int $flags)/*: array|false*/
    {
        // if this flag is set, your wrapper should not raise any errors.
        $shouldReport = !($flags & STREAM_URL_STAT_QUIET);
        set_error_handler(fn() => !$shouldReport);

        try {
            if ($flags & STREAM_URL_STAT_LINK) {
                return $this->_lstat($path);
            }
            else {
                return $this->_stat($path);
            }
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
        finally {
            restore_error_handler();
        }
    }

    public function stream_metadata(string $path, int $option, /*mixed*/ $value): bool
    {
        try {
            clearstatcache(true, $path);

            switch ($option) {
                case STREAM_META_TOUCH:
                    // mtime: the touch time. If mtime is null, the current system time() is used.
                    // atime: it is set to the value passed to the mtime parameter. If both are null, the current system time is used.
                    return $this->_touch($path, $value[0] ?? time(), $value[1] ?? $value[0] ?? time());

                case STREAM_META_ACCESS:
                    return $this->_chmod($path, $value);

                /** @noinspection PhpMissingBreakStatementInspection */
                case STREAM_META_OWNER_NAME:
                    $value = posix_getpwnam($value)['uid'];
                case STREAM_META_OWNER:
                    return $this->_chown($path, $value);

                /** @noinspection PhpMissingBreakStatementInspection */
                case STREAM_META_GROUP_NAME:
                    $value = posix_getgrnam($value)['gid'];
                case STREAM_META_GROUP:
                    return $this->_chgrp($path, $value);
            }
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function dir_opendir(string $path, int $options): bool
    {
        // No detailed description exists in php documentation
        assert($options === 0);

        try {
            $this->___resource = $this->_opendir($path, ...$this->___getContextParamsByUrl($path));
            return true;
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function dir_readdir()/*:false|string*/
    {
        try {
            return $this->_readdir($this->___resource) ?? false;
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function dir_rewinddir(): bool
    {
        try {
            return $this->_rewinddir($this->___resource);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function dir_closedir(): bool
    {
        try {
            return $this->_closedir($this->___resource);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        // if this flag is not set, you should not raise any errors.
        $shouldReport = (defined('STREAM_WRAPPER_DEBUG') && STREAM_WRAPPER_DEBUG) || ($options & STREAM_REPORT_ERRORS);
        set_error_handler(fn() => !$shouldReport);

        try {
            $this->___resource = $this->_fopen($path, $mode, ...$this->___getContextParamsByUrl($path));

            // if the path is opened successfully, and STREAM_USE_PATH is set in options
            // opened_path should be set to the full path of the file/resource that was actually opened.
            if (($options & STREAM_USE_PATH) && isset($this->___resource->url)) {
                $opened_path = $this->___resource->url;
            }

            return true;
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
        finally {
            restore_error_handler();
        }
    }

    public function stream_lock(int $operation): bool
    {
        try {
            return $this->_flock($this->___resource, $operation);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_tell(): int
    {
        try {
            return $this->_ftell($this->___resource);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        try {
            if (!in_array($whence, [SEEK_SET, SEEK_CUR, SEEK_END], true)) {
                ErrorException::throwDebug("fseek(): whence($whence) is invalid. must be either[SEEK_SET, SEEK_CUR, SEEK_END]");
            }
            return $this->_fseek($this->___resource, $offset, $whence);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_eof(): bool
    {
        try {
            return $this->_feof($this->___resource);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_read(int $count)/*: string|false*/
    {
        try {
            if ($count <= 0) {
                ErrorException::throwWarning("fread(): Length parameter must be greater than 0");
            }
            return $this->_fread($this->___resource, $count);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_write(string $data): int
    {
        try {
            return $this->_fwrite($this->___resource, $data);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_truncate(int $new_size): bool
    {
        try {
            if ($new_size < 0) {
                ErrorException::throwWarning("ftruncate(): Negative size is not supported");
            }
            return $this->_ftruncate($this->___resource, $new_size);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_flush(): bool
    {
        try {
            if (isset($this->___resource->url)) {
                clearstatcache(true, $this->___resource->url);
            }
            return $this->_fflush($this->___resource);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_close(): void
    {
        try {
            $this->_fclose($this->___resource);
        }
        catch (ErrorException $e) {
            $e->trigger();
        }
    }

    public function stream_stat()/*: array|false*/
    {
        try {
            return $this->_fstat($this->___resource);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        try {
            switch ($option) {
                case STREAM_OPTION_BLOCKING:
                    return $this->_stream_set_blocking($this->___resource, $arg1);

                case STREAM_OPTION_READ_BUFFER:
                    return $this->_stream_set_read_buffer($this->___resource, $arg2);

                case STREAM_OPTION_WRITE_BUFFER:
                    return $this->_stream_set_write_buffer($this->___resource, $arg2);

                case STREAM_OPTION_READ_TIMEOUT:
                    return $this->_stream_set_timeout($this->___resource, $arg1 + $arg2 / 1_000_000);
            }
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    public function stream_cast(int $cast_as)/*: resource*/
    {
        try {
            return $this->_stream_select($this->___resource, $cast_as);
        }
        catch (ErrorException $e) {
            return $e->trigger();
        }
    }

    private function ___getContextParamsByUrl(string $url): array
    {
        if (preg_match('#^([a-z][-+.0-9a-z]*)://#', $url, $matches)) {
            $params  = array_replace_recursive(
                stream_context_get_params(stream_context_get_default()),
                is_resource($this->context) ? stream_context_get_params($this->context) : [],
            );
            $options = $params['options'][$matches[1]] ?? [];
            unset($params['options']);
            return [$options, $params];
        }
        return [[], []];
    }
}
