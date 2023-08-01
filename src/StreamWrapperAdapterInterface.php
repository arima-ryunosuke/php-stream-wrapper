<?php
namespace ryunosuke\StreamWrapper;

/**
 * Adapter interface this package to php stream wrapper
 */
interface StreamWrapperAdapterInterface
{
    // <editor-fold desc="DirectoryIO">

    /** @see https://www.php.net/manual/function.mkdir.php */
    public function _mkdir(string $url, int $permissions, bool $recursive, array $contextOptions, array $contextParams): bool;

    /** @see https://www.php.net/manual/function.rmdir.php */
    public function _rmdir(string $url, array $contextOptions, array $contextParams): bool;

    // </editor-fold>

    // <editor-fold desc="UrlIO">

    /** @see https://www.php.net/manual/function.touch.php */
    public function _touch(string $url, int $mtime, int $atime): bool;

    /** @see https://www.php.net/manual/function.unlink.php */
    public function _unlink(string $url, array $contextOptions, array $contextParams): bool;

    /** @see https://www.php.net/manual/function.rename.php */
    public function _rename(string $src_url, string $dst_url, array $contextOptions, array $contextParams): bool;

    /** @see https://www.php.net/manual/function.stat.php */
    public function _stat(string $url): array;

    /** @see https://www.php.net/manual/function.lstat.php */
    public function _lstat(string $url): array;

    // </editor-fold>

    // <editor-fold desc="UrlPermission">

    /** @see https://www.php.net/manual/function.chmod.php */
    public function _chmod(string $url, int $permissions): bool;

    /** @see https://www.php.net/manual/function.chown.php */
    public function _chown(string $url, int $uid): bool;

    /** @see https://www.php.net/manual/function.chgrp.php */
    public function _chgrp(string $url, int $gid): bool;

    // </editor-fold>

    // <editor-fold desc="DirectoryIterator">

    /** @see https://www.php.net/manual/function.opendir.php */
    public function _opendir(string $url, array $contextOptions, array $contextParams): object;

    /** @see https://www.php.net/manual/function.readdir.php */
    public function _readdir(object $resource): ?string;

    /** @see https://www.php.net/manual/function.rewinddir.php */
    public function _rewinddir(object $resource): bool;

    /** @see https://www.php.net/manual/function.closedir.php */
    public function _closedir(object $resource): bool;

    // </editor-fold>

    // <editor-fold desc="StreamIO">

    /** @see https://www.php.net/manual/function.fopen.php */
    public function _fopen(string $url, string $mode, array $contextOptions, array $contextParams): object;

    /** @see https://www.php.net/manual/function.fread.php */
    public function _fread(object $resource, int $length): string;

    /** @see https://www.php.net/manual/function.fwrite.php */
    public function _fwrite(object $resource, string $data): int;

    /** @see https://www.php.net/manual/function.ftruncate.php */
    public function _ftruncate(object $resource, int $size): bool;

    /** @see https://www.php.net/manual/function.fclose.php */
    public function _fclose(object $resource): bool;

    // </editor-fold>

    // <editor-fold desc="StreamSeek">

    /** @see https://www.php.net/manual/function.ftell.php */
    public function _ftell(object $resource): int;

    /** @see https://www.php.net/manual/function.fseek.php */
    public function _fseek(object $resource, int $offset, int $whence): bool;

    /** @see https://www.php.net/manual/function.feof.php */
    public function _feof(object $resource): bool;

    // </editor-fold>

    // <editor-fold desc="StreamMeta">

    /** @see https://www.php.net/manual/function.fflush.php */
    public function _fflush(object $resource): bool;

    /** @see https://www.php.net/manual/function.flock.php */
    public function _flock(object $resource, int $operation): bool;

    /** @see https://www.php.net/manual/function.fstat.php */
    public function _fstat(object $resource): array;

    // </editor-fold>

    // <editor-fold desc="StreamOption">

    /** @see https://www.php.net/manual/function.stream-set-blocking.php */
    public function _stream_set_blocking(object $resource, bool $enable): bool;

    /** @see https://www.php.net/manual/function.stream-set-read-buffer.php */
    public function _stream_set_read_buffer(object $resource, int $size): bool;

    /** @see https://www.php.net/manual/function.stream-set-write-buffer.php */
    public function _stream_set_write_buffer(object $resource, int $size): bool;

    /** @see https://www.php.net/manual/function.stream-set-timeout.php */
    public function _stream_set_timeout(object $resource, float $timeout): bool;

    // </editor-fold>

    // <editor-fold desc="StreamSelect">

    /** @see https://www.php.net/manual/function.stream-select.php */
    public function _stream_select(object $resource, int $cast_as)/*: resource*/ ;

    // </editor-fold>
}
