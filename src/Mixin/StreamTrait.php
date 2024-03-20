<?php
namespace ryunosuke\StreamWrapper\Mixin;

use LogicException;
use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Exception\NotImplementException;
use ryunosuke\StreamWrapper\Utils\Mode;
use ryunosuke\StreamWrapper\Utils\Resource;
use ryunosuke\StreamWrapper\Utils\Stat;

trait StreamTrait
{
    abstract protected function getMetadata(string $url): ?array;

    abstract protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string;

    abstract protected function createFile(string $url, string $contents, array $metadata, array $contextOptions): void;

    abstract protected function appendFile(string $url, string $contents, array $contextOptions): void;

    public function _fopen(string $url, string $mode, array $contextOptions, array $contextParams): Resource
    {
        $mode = ErrorException::convert(fn() => new Mode($mode), LogicException::class);

        $uid = function_exists('posix_getuid') ? posix_getuid() : null;
        $gid = function_exists('posix_getgid') ? posix_getgid() : null;

        $checkPermission = function (Mode $mode, Stat $stat) use ($url, $uid, $gid) {
            if (($mode->isReadable() && !$stat->isReadable($uid, $gid)) || ($mode->isWritable() && !$stat->isWritable($uid, $gid))) {
                ErrorException::throwWarning("$url: permission denied");
            }
        };

        if ($mode->isReadMode()) {
            // place the file pointer at the beginning of the file.
            $contents = $this->selectFile($url, $meta, $contextOptions);
            $stat     = new Stat($meta);
            $checkPermission($mode, $stat);
            return new Resource([
                'url'       => $url,
                'mode'      => $mode,
                'stat'      => $stat,
                'options'   => $contextOptions,
                'position'  => 0,
                'contents'  => $contents,
                'flushed'   => false,
                'locked'    => 0,
                'blocking'  => true,
                'readSize'  => 0,
                'writeSize' => 0,
                'timeout'   => 0,
            ]);
        }
        if ($mode->isWriteMode()) {
            // place the file pointer at the beginning of the file and truncate the file to zero length.
            // if the file does not exist, attempt to create it.
            $meta = $this->getMetadata($url);
            $stat = new Stat(['mtime' => time()] + ($meta ?? ['uid' => $uid, 'gid' => $gid, 'ctime' => time()]));
            $checkPermission($mode, $stat);
            $this->createFile($url, '', $stat->array(), $contextOptions);
            return new Resource([
                'url'       => $url,
                'mode'      => $mode,
                'stat'      => $stat,
                'options'   => $contextOptions,
                'position'  => 0,
                'contents'  => '',
                'flushed'   => false,
                'locked'    => 0,
                'blocking'  => true,
                'readSize'  => 0,
                'writeSize' => 0,
                'timeout'   => 0,
            ]);
        }
        if ($mode->isExcludeMode()) {
            // place the file pointer at the beginning of the file.
            // if the file already exists, the fopen() call will fail by returning false and generating an error of level E_WARNING.
            // if the file does not exist, attempt to create it.
            $meta = $this->getMetadata($url);
            if ($meta !== null) {
                ErrorException::throwWarning("$url: already is exists");
            }
            $stat = new Stat(['uid' => $uid, 'gid' => $gid, 'mtime' => time(), 'ctime' => time()]);
            $checkPermission($mode, $stat);
            $this->createFile($url, '', $stat->array(), $contextOptions);
            return new Resource([
                'url'       => $url,
                'mode'      => $mode,
                'stat'      => $stat,
                'options'   => $contextOptions,
                'position'  => 0,
                'contents'  => '',
                'flushed'   => false,
                'locked'    => 0,
                'blocking'  => true,
                'readSize'  => 0,
                'writeSize' => 0,
                'timeout'   => 0,
            ]);
        }
        if ($mode->isCreateMode()) {
            // if the file does not exist, it is created.
            // if it exists, it is neither truncated (as opposed to 'w'), nor the call to this function fails (as is the case with 'x').
            // the file pointer is positioned on the beginning of the file.
            $contents = ErrorException::suppress(function () use ($url, &$meta, $contextOptions) { return $this->selectFile($url, $meta, $contextOptions); }, '');
            $stat     = new Stat($meta ?? ['uid' => $uid, 'gid' => $gid, 'mtime' => time(), 'ctime' => time()]);
            $checkPermission($mode, $stat);
            if ($meta === null) {
                $this->createFile($url, '', $stat->array(), $contextOptions);
            }
            return new Resource([
                'url'       => $url,
                'mode'      => $mode,
                'stat'      => $stat,
                'options'   => $contextOptions,
                'position'  => 0,
                'contents'  => $contents,
                'flushed'   => false,
                'locked'    => 0,
                'blocking'  => true,
                'readSize'  => 0,
                'writeSize' => 0,
                'timeout'   => 0,
            ]);
        }
        if ($mode->isAppendMode()) {
            // place the file pointer at the end of the file.
            // if the file does not exist, attempt to create it.
            // a : in this mode, fseek() has no effect, writes are always appended.
            // a+: in this mode, fseek() only affects the reading position, writes are always appended.
            $meta = $this->getMetadata($url);
            $stat = new Stat($meta ?? ['uid' => $uid, 'gid' => $gid, 'mtime' => time(), 'ctime' => time()]);
            $checkPermission($mode, $stat);
            if ($meta === null) {
                $this->createFile($url, '', $stat->array(), $contextOptions);
            }
            return new Resource([
                'url'       => $url,
                'mode'      => $mode,
                'stat'      => $stat,
                'options'   => $contextOptions,
                'position'  => $meta['size'] ?? 0,
                'appendix'  => '',
                'flushed'   => false,
                'locked'    => 0,
                'blocking'  => true,
                'readSize'  => 0,
                'writeSize' => 0,
                'timeout'   => 0,
            ]);
        }
    }

    public function _fread(object/*|Resource*/ $resource, int $length): string
    {
        /** @var Resource $resource */
        if (!$resource->mode->isReadable()) {
            ErrorException::throwWarning('failed with errno=9 Bad file descriptor');
        }

        // this is lazy read. in a+ mode, the content is not necessary unless want to read.
        if ($resource->mode->isAppendable()) {
            $resource->contents ??= $this->selectFile($resource->url, $dummy, $resource->options);
            $buffer             = $resource->contents . $resource->appendix;
        }
        else {
            $buffer = $resource->contents;
        }

        $result             = substr($buffer, $resource->position, $length);
        $resource->position += strlen($result);
        return $result;
    }

    public function _fwrite(object/*|Resource*/ $resource, string $data): int
    {
        /** @var Resource $resource */
        if (!$resource->mode->isWritable()) {
            ErrorException::throwWarning('failed with errno=9 Bad file descriptor');
        }

        $datalen = strlen($data);

        $resource->flushed     = false;
        $resource->stat->mtime = time();

        // in this mode, fseek() has no effect, writes are always appended.
        if ($resource->mode->isAppendable()) {
            $resource->appendix   .= $data;
            $resource->stat->size += $datalen;
            return $datalen;
        }

        // in general, it is allowed to seek past the end-of-file
        // if data is then written, reads in any unwritten region between the end-of-file and the sought position will yield bytes with value 0
        $current              = str_pad($resource->contents, $resource->position, "\0", STR_PAD_RIGHT);
        $resource->contents   = substr_replace($current, $data, $resource->position, $datalen);
        $resource->position   += $datalen;
        $resource->stat->size = strlen($resource->contents);
        return $datalen;
    }

    public function _ftruncate(object/*|Resource*/ $resource, int $size): bool
    {
        /** @var Resource $resource */
        if (!$resource->mode->isWritable()) {
            ErrorException::throwDebug('failed with errno=9 Bad file descriptor');
        }

        $resource->flushed     = false;
        $resource->stat->mtime = time();

        // can truncate in mode "a" (standard functions as well)
        if ($resource->mode->isAppendable()) {
            $resource->contents ??= $size === 0 ? '' : $this->selectFile($resource->url, $dummy, $resource->options);
            $resource->contents .= $resource->appendix;
            $resource->appendix = '';
        }

        // if size is larger than the file then the file is extended with null bytes.
        $buffer = $resource->contents;
        if (($oversize = $size - $resource->stat->size) > 0) {
            $buffer .= str_repeat("\0", $oversize);
        }

        $resource->contents   = substr($buffer, 0, $size);
        $resource->stat->size = $size;
        return true;
    }

    public function _ftell(object/*|Resource*/ $resource): int
    {
        /** @var Resource $resource */
        return $resource->position;
    }

    public function _fseek(object/*|Resource*/ $resource, int $offset, int $whence): bool
    {
        /** @var Resource $resource */
        $position = $resource->position;
        if ($whence === SEEK_SET) {
            $position = $offset;
        }
        if ($whence === SEEK_CUR) {
            $position += $offset;
        }
        if ($whence === SEEK_END) {
            $position = $resource->stat->size + $offset;
        }
        if ($position < 0) {
            ErrorException::throwDebug('failed to fseek position < 0');
        }

        $resource->position = $position;
        return true;
    }

    public function _feof(object/*|Resource*/ $resource): bool
    {
        /** @var Resource $resource */
        return $resource->position >= $resource->stat->size;
    }

    public function _fflush(object/*|Resource*/ $resource): bool
    {
        /** @var Resource $resource */
        if ($resource->flushed || !$resource->mode->isWritable()) {
            return true;
        }

        $resource->flushed = true;

        if ($resource->mode->isAppendable()) {
            // for truncate
            if (isset($resource->contents)) {
                $this->createFile($resource->url, $resource->contents . $resource->appendix, $resource->stat->array(), $resource->options);
                unset($resource->contents);
            }
            else {
                $this->appendFile($resource->url, $resource->appendix, $resource->options);
            }
            $resource->appendix = '';
        }
        else {
            $this->createFile($resource->url, $resource->contents, $resource->stat->array(), $resource->options);
        }
        return true;
    }

    public function _fclose(object/*|Resource*/ $resource): bool
    {
        /** @var Resource $resource */
        $this->_fflush($resource);
        return true;
    }

    public function _fstat(object/*|Resource*/ $resource): array
    {
        /** @var Resource $resource */
        return $resource->stat->array();
    }

    public function _flock(object/*Resource*/ $resource, int $operation): bool
    {
        /** @var Resource $resource */
        $resource->locked = $operation;
        return true;
    }

    public function _stream_set_blocking(object $resource, bool $enable): bool
    {
        /** @var Resource $resource */
        // this field is not used in this trait, but the user is free to use this field as they wish.
        $resource->blocking = $enable;
        return true;
    }

    public function _stream_set_read_buffer(object/*|Resource*/ $resource, int $size): bool
    {
        /** @var Resource $resource */
        // this field is same to _stream_set_blocking
        $resource->readSize = $size;
        return true;
    }

    public function _stream_set_write_buffer(object/*|Resource*/ $resource, int $size): bool
    {
        /** @var Resource $resource */
        // this field is same to _stream_set_blocking
        $resource->writeSize = $size;
        return true;
    }

    public function _stream_set_timeout(object $resource, float $timeout): bool
    {
        /** @var Resource $resource */
        // this field is same to _stream_set_blocking
        $resource->timeout = $timeout;
        return true;
    }
}
