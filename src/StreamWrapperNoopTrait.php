<?php
namespace ryunosuke\StreamWrapper;

use ryunosuke\StreamWrapper\Exception\NotImplementException;

/**
 * Default implementation all Interfaces
 */
trait StreamWrapperNoopTrait
{
    public function _mkdir(string $url, int $permissions, bool $recursive, array $contextOptions, array $contextParams): bool { NotImplementException::throw(); }

    public function _rmdir(string $url, array $contextOptions, array $contextParams): bool { NotImplementException::throw(); }

    public function _touch(string $url, int $mtime, int $atime): bool { NotImplementException::throw(); }

    public function _unlink(string $url, array $contextOptions, array $contextParams): bool { NotImplementException::throw(); }

    public function _rename(string $src_url, string $dst_url, array $contextOptions, array $contextParams): bool { NotImplementException::throw(); }

    public function _stat(string $url): array { NotImplementException::throw(); }

    public function _lstat(string $url): array { return $this->_stat($url); }

    public function _chmod(string $url, int $permissions): bool { NotImplementException::throw(); }

    public function _chown(string $url, int $uid): bool { NotImplementException::throw(); }

    public function _chgrp(string $url, int $gid): bool { NotImplementException::throw(); }

    public function _opendir(string $url, array $contextOptions, array $contextParams): object { NotImplementException::throw(); }

    public function _readdir(object $resource): ?string { NotImplementException::throw(); }

    public function _rewinddir(object $resource): bool { NotImplementException::throw(); }

    public function _closedir(object $resource): bool { NotImplementException::throw(); }

    public function _fopen(string $url, string $mode, array $contextOptions, array $contextParams): object { NotImplementException::throw(); }

    public function _fread(object $resource, int $length): string { NotImplementException::throw(); }

    public function _fwrite(object $resource, string $data): int { NotImplementException::throw(); }

    public function _ftruncate(object $resource, int $size): bool { NotImplementException::throw(); }

    public function _fclose(object $resource): bool { NotImplementException::throw(); }

    public function _ftell(object $resource): int { NotImplementException::throw(); }

    public function _fseek(object $resource, int $offset, int $whence): bool { NotImplementException::throw(); }

    public function _feof(object $resource): bool { NotImplementException::throw(); }

    public function _fflush(object $resource): bool { NotImplementException::throw(); }

    public function _flock(object $resource, int $operation): bool { NotImplementException::throw(); }

    public function _fstat(object $resource): array { NotImplementException::throw(); }

    public function _stream_set_blocking(object $resource, bool $enable): bool { NotImplementException::throw(); }

    public function _stream_set_read_buffer(object $resource, int $size): bool { NotImplementException::throw(); }

    public function _stream_set_write_buffer(object $resource, int $size): bool { NotImplementException::throw(); }

    public function _stream_set_timeout(object $resource, float $timeout): bool { NotImplementException::throw(); }

    public function _stream_select(object $resource, int $cast_as)/*: resource*/
    {
        // stream_cast(by stream_select) is not necessary to implement in most use cases.
        // and it is also poorly documented and difficult to implement.
        // however, it is sometimes called (e.g. mime_content_type), so it is implemented false as default.
        return false;
    }
}
