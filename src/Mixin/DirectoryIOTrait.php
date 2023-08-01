<?php
namespace ryunosuke\StreamWrapper\Mixin;

use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Utils\Stat;

trait DirectoryIOTrait
{
    protected function parents(string $url, array $contextOptions): iterable
    {
        for ($parent = $this->parent($url, $contextOptions); $parent !== null; $parent = $this->parent($parent, $contextOptions)) {
            $meta = $this->getMetadata($parent);
            if ($meta === null) {
                yield $parent => null;
            }
            else {
                yield $parent => (new Stat($meta))->filetype();
            }
        }
    }

    abstract protected function parent(string $url, array $contextOptions): ?string;

    abstract protected function children(string $url, array $contextOptions): iterable;

    abstract protected function getMetadata(string $url): ?array;

    abstract protected function createDirectory(string $url, int $permissions, array $contextOptions): void;

    abstract protected function deleteDirectory(string $url, array $contextOptions): void;

    public function _mkdir(string $url, int $permissions, bool $recursive, array $contextOptions, array $contextParams): bool
    {
        if ($this->getMetadata($url) !== null) {
            ErrorException::throwWarning("$url: exists already");
        }

        if ($recursive) {
            $parents = [];
            foreach ($this->parents($url, $contextOptions) as $purl => $parent) {
                if ($parent === 'dir') {
                    break;
                }
                if ($parent === null) {
                    $parents[] = $purl;
                }
                else {
                    ErrorException::throwWarning("$purl: is not directory");
                }
            }
            foreach (array_reverse($parents) as $parent) {
                $this->createDirectory($parent, $permissions, $contextOptions);
            }
        }
        else {
            foreach ($this->parents($url, $contextOptions) as $purl => $parent) {
                if ($parent !== 'dir') {
                    ErrorException::throwWarning("$purl: does not exist or not directory");
                }
                break;
            }
        }

        $this->createDirectory($url, $permissions, $contextOptions);
        return true;
    }

    public function _rmdir(string $url, array $contextOptions, array $contextParams): bool
    {
        foreach ($this->children($url, $contextOptions) as $child) {
            if (is_string($child) && in_array($child, ['.', '..'], true)) {
                continue; // @codeCoverageIgnore
            }
            ErrorException::throwWarning("$url: is not empty");
        }

        $this->deleteDirectory($url, $contextOptions);
        return true;
    }
}
