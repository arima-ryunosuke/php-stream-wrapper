<?php
namespace ryunosuke\StreamWrapper\Mixin;

use ArrayIterator;
use ryunosuke\StreamWrapper\Utils\Resource;

trait DirectoryIteratorTrait
{
    abstract protected function children(string $url, array $contextOptions): iterable;

    protected function iterableFilterLocalPath(string $parent, iterable $pathes, callable $map = null): array
    {
        $parent = '/' . trim($parent, '/');

        $result = [];
        foreach ($pathes as $path) {
            $path     = $map ? $map($path) : $path;
            $path     = '/' . trim($path, '/') . '/';
            $result[] = $path;
        }

        $result = preg_filter('#^' . preg_quote($parent) . '/?([^/]+).*$#u', '$1', $result);
        $result = array_unique($result);
        sort($result);

        return $result;
    }

    public function _opendir(string $url, array $contextOptions, array $contextParams): Resource
    {
        // RecursiveIteratorIterator calls rewind so have to convert to ArrayIterator
        $traversable = new ArrayIterator([...$this->children($url, $contextOptions)]);
        return new Resource(['url' => $url, 'iterator' => $traversable]);
    }

    public function _rewinddir(object/*|Resource*/ $resource): bool
    {
        /** @var Resource $resource */
        $resource->iterator->rewind();
        return true;
    }

    public function _readdir(object/*|Resource*/ $resource): ?string
    {
        /** @var Resource $resource */
        if (!$resource->iterator->valid()) {
            return null;
        }
        $current = $resource->iterator->current();
        $resource->iterator->next();
        return substr($current, strlen($resource->url) + 1);
    }

    public function _closedir(object/*|Resource*/ $resource): bool
    {
        /** @var Resource $resource */
        unset($resource->iterator);
        return true;
    }
}
