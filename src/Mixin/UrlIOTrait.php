<?php
namespace ryunosuke\StreamWrapper\Mixin;

use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Utils\Stat;

trait UrlIOTrait
{
    protected function intermove(string $src_url, string $dst_url, array $contextOptions): void
    {
        $value = $this->selectFile($src_url, $metadata, $contextOptions);
        ErrorException::suppress(fn() => $this->deleteFile($dst_url, $contextOptions));
        $this->createFile($dst_url, $value, $metadata, $contextOptions);
        $this->deleteFile($src_url, $contextOptions);
    }

    abstract protected function move(string $src_url, string $dst_url, array $contextOptions): void;

    abstract protected function getMetadata(string $url): ?array;

    abstract protected function setMetadata(string $url, array $metadata): void;

    abstract protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string;

    abstract protected function createFile(string $url, string $contents, array $metadata, array $contextOptions): void;

    abstract protected function deleteFile(string $url, array $contextOptions): void;

    public function _touch(string $url, int $mtime, int $atime): bool
    {
        if ($this->getMetadata($url) === null) {
            $stat = new Stat([
                'mtime' => $mtime,
                'atime' => $atime,
                'ctime' => time(),
            ]);
            $this->createFile($url, '', $stat->array(), []);
        }
        else {
            $stat = new Stat([]);
            $this->setMetadata($url, $stat->touch($mtime, $atime));
        }
        return true;
    }

    public function _unlink(string $url, array $contextOptions, array $contextParams): bool
    {
        $this->deleteFile($url, $contextOptions);
        return true;
    }

    public function _rename(string $src_url, string $dst_url, array $contextOptions, array $contextParams): bool
    {
        $this->move($src_url, $dst_url, $contextOptions);
        return true;
    }

    public function _stat(string $url): array
    {
        $metadata = $this->getMetadata($url);
        if ($metadata === null) {
            ErrorException::throwWarning("$url: failed to stat");
        }
        return (new Stat($metadata))->array();
    }
}
