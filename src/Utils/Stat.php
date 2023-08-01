<?php
namespace ryunosuke\StreamWrapper\Utils;

/**
 * @property $dev
 * @property $ino
 * @property $mode
 * @property $nlink
 * @property $uid
 * @property $gid
 * @property $rdev
 * @property $size
 * @property $atime
 * @property $mtime
 * @property $ctime
 * @property $blksize
 * @property $blocks
 */
class Stat
{
    public const FIFO   = 001_0000;
    public const CHAR   = 002_0000;
    public const DIR    = 004_0000;
    public const BLOCK  = 006_0000;
    public const FILE   = 010_0000;
    public const LINK   = 012_0000;
    public const SOCKET = 014_0000;

    public const READABLE   = 04;
    public const WRITABLE   = 02;
    public const EXECUTABLE = 01;

    public const OWNER = 0100;
    public const GROUP = 0010;
    public const OTHER = 0001;

    private int $dev     = 0;
    private int $ino     = 0;
    private int $mode    = self::FILE | 0777;
    private int $nlink   = 1;
    private int $uid     = 0;
    private int $gid     = 0;
    private int $rdev    = -1;
    private int $size    = 0;
    private int $atime   = 0;
    private int $mtime   = 0;
    private int $ctime   = 0;
    private int $blksize = -1;
    private int $blocks  = -1;

    public function __construct(array $stat)
    {
        foreach ($stat as $name => $value) {
            if (property_exists($this, $name)) {
                $this->$name = $value ?? 0;
            }
        }
    }

    public function __get($name)
    {
        assert(property_exists($this, $name));
        return $this->$name;
    }

    public function __set($name, $value)
    {
        assert(property_exists($this, $name));
        $this->$name = $value;
    }

    public function array(): array
    {
        return [
            'dev'     => $this->dev,
            'ino'     => $this->ino,
            'mode'    => $this->mode,
            'nlink'   => $this->nlink,
            'uid'     => $this->uid,
            'gid'     => $this->gid,
            'rdev'    => $this->rdev,
            'size'    => $this->size,
            'atime'   => $this->atime,
            'mtime'   => $this->mtime,
            'ctime'   => $this->ctime,
            'blksize' => $this->blksize,
            'blocks'  => $this->blocks,
        ];
    }

    public function filetype(): string
    {
        $types = [
            self:: FIFO   => 'fifo',
            self:: CHAR   => 'char',
            self:: DIR    => 'dir',
            self:: BLOCK  => 'block',
            self:: FILE   => 'file',
            self:: LINK   => 'link',
            self:: SOCKET => 'socket',
        ];

        foreach ($types as $type => $result) {
            if (($this->mode & 077_0000) === $type) {
                return $result;
            }
        }

        return 'unknown';
    }

    public function touch(?int $mtime, ?int $atime): array
    {
        if ($mtime === null && $atime === null) {
            return [];
        }

        $result = [];

        if ($mtime !== null) {
            $result['mtime'] = $this->mtime = $mtime;
        }
        if ($atime !== null) {
            $result['atime'] = $this->atime = $atime;
        }

        $result['ctime'] = $this->ctime = time();

        return $result;
    }

    public function chmod(int $permission): array
    {
        return [
            'mode'  => $this->mode = ($this->mode & 077_0000) | ($permission & ~umask()),
            'ctime' => $this->ctime = time(),
        ];
    }

    public function chown(int $uid): array
    {
        return [
            'uid'   => $this->uid = $uid,
            'ctime' => $this->ctime = time(),
        ];
    }

    public function chgrp(int $gid): array
    {
        return [
            'gid'   => $this->gid = $gid,
            'ctime' => $this->ctime = time(),
        ];
    }

    public function isReadable(?int $uid, ?int $gid): bool
    {
        return $this->isAble($uid, $gid, self::READABLE);
    }

    public function isWritable(?int $uid, ?int $gid): bool
    {
        return $this->isAble($uid, $gid, self::WRITABLE);
    }

    public function isExecutable(?int $uid, ?int $gid): bool
    {
        return $this->isAble($uid, $gid, self::EXECUTABLE);
    }

    private function isAble(?int $uid, ?int $gid, int $rwx): bool
    {
        // special treatment
        if ($uid === 0) {
            return true; // @codeCoverageIgnore
        }

        if ($this->uid === $uid && $this->mode & $rwx * self::OWNER) {
            return true;
        }
        if ($this->gid === $gid && $this->mode & $rwx * self::GROUP) {
            return true;
        }
        return $this->uid !== $uid && $this->gid !== $gid && $this->mode & $rwx * self::OTHER;
    }
}
