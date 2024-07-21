<?php
namespace ryunosuke\StreamWrapper\Mixin;

use ryunosuke\StreamWrapper\StreamWrapperAdapterTrait;
use ryunosuke\StreamWrapper\StreamWrapperNoopTrait;
use ryunosuke\StreamWrapper\Utils\Resource;

/**
 * Delegate trait this package to something stream wrapper
 */
trait DelegateTrait
{
    use StreamWrapperAdapterTrait;
    use StreamWrapperNoopTrait;

    abstract protected function delegate(string $function, ...$args);

    protected function ___getUrlArgs(string $function): array
    {
        switch ($function) {
            default:
                return [];

            case 'mkdir':
            case 'rmdir':
            case 'touch':
            case 'unlink':
            case 'stat':
            case 'lstat':
            case 'chmod':
            case 'chown':
            case 'chgrp':
            case 'opendir':
            case 'fopen':
                return [0];

            case 'rename':
                return [0, 1];
        }
    }

    public function _mkdir(string $url, int $permissions, bool $recursive, array $contextOptions, array $contextParams): bool
    {
        return $this->delegate('mkdir', $url, $permissions, $recursive, $this->context);
    }

    public function _rmdir(string $url, array $contextOptions, array $contextParams): bool
    {
        return $this->delegate('rmdir', $url, $this->context);
    }

    public function _touch(string $url, int $mtime, int $atime): bool
    {
        return $this->delegate('touch', $url, $mtime, $atime);
    }

    public function _unlink(string $url, array $contextOptions, array $contextParams): bool
    {
        return $this->delegate('unlink', $url, $this->context);
    }

    public function _rename(string $src_url, string $dst_url, array $contextOptions, array $contextParams): bool
    {
        return $this->delegate('rename', $src_url, $dst_url, $this->context);
    }

    public function _stat(string $url): array
    {
        return $this->delegate('stat', $url);
    }

    public function _lstat(string $url): array
    {
        return $this->delegate('lstat', $url);
    }

    public function _chmod(string $url, int $permissions): bool
    {
        return $this->delegate('chmod', $url, $permissions);
    }

    public function _chown(string $url, int $uid): bool
    {
        return $this->delegate('chown', $url, $uid);
    }

    public function _chgrp(string $url, int $gid): bool
    {
        return $this->delegate('chgrp', $url, $gid);
    }

    public function _opendir(string $url, array $contextOptions, array $contextParams): object
    {
        $args = $this->context === null ? [] : [$this->context]; // for compatible. under php8
        return new Resource(['handle' => $this->delegate('opendir', $url, ...$args)]);
    }

    public function _readdir(object $resource): ?string
    {
        $result = $this->delegate('readdir', $resource->handle);
        return $result === false ? null : $result;
    }

    public function _rewinddir(object $resource): bool
    {
        $this->delegate('rewinddir', $resource->handle);
        return true;
    }

    public function _closedir(object $resource): bool
    {
        $this->delegate('closedir', $resource->handle);
        return true;
    }

    public function _fopen(string $url, string $mode, array $contextOptions, array $contextParams): object
    {
        return new Resource(['handle' => $this->delegate('fopen', $url, $mode, false, $this->context)]);
    }

    public function _fread(object $resource, int $length): string
    {
        return $this->delegate('fread', $resource->handle, $length);
    }

    public function _fwrite(object $resource, string $data): int
    {
        return $this->delegate('fwrite', $resource->handle, $data);
    }

    public function _ftruncate(object $resource, int $size): bool
    {
        return $this->delegate('ftruncate', $resource->handle, $size);
    }

    public function _fclose(object $resource): bool
    {
        return $this->delegate('fclose', $resource->handle);
    }

    public function _ftell(object $resource): int
    {
        return $this->delegate('ftell', $resource->handle);
    }

    public function _fseek(object $resource, int $offset, int $whence): bool
    {
        return $this->delegate('fseek', $resource->handle, $offset, $whence) === 0;
    }

    public function _feof(object $resource): bool
    {
        return $this->delegate('feof', $resource->handle);
    }

    public function _fflush(object $resource): bool
    {
        return $this->delegate('fflush', $resource->handle);
    }

    public function _flock(object $resource, int $operation): bool
    {
        return $this->delegate('flock', $resource->handle, $operation);
    }

    public function _fstat(object $resource): array
    {
        return $this->delegate('fstat', $resource->handle);
    }

    public function _stream_set_blocking(object $resource, bool $enable): bool
    {
        return $this->delegate('stream_set_blocking', $resource->handle, $enable);
    }

    public function _stream_set_read_buffer(object $resource, int $size): bool
    {
        return $this->delegate('stream_set_read_buffer', $resource->handle, $size) === 0;
    }

    public function _stream_set_write_buffer(object $resource, int $size): bool
    {
        return $this->delegate('stream_set_write_buffer', $resource->handle, $size) === 0;
    }

    public function _stream_set_timeout(object $resource, float $timeout): bool
    {
        return $this->delegate('stream_set_timeout', $resource->handle, $timeout);
    }
}
