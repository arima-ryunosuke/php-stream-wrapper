<?php
namespace ryunosuke\StreamWrapper\Stream;

use IteratorAggregate;
use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\DirectoryIOTrait;
use ryunosuke\StreamWrapper\Mixin\DirectoryIteratorTrait;
use ryunosuke\StreamWrapper\Mixin\StreamTrait;
use ryunosuke\StreamWrapper\Mixin\UrlIOTrait;
use ryunosuke\StreamWrapper\Mixin\UrlPermissionTrait;
use ryunosuke\StreamWrapper\Utils\Stat;
use ryunosuke\StreamWrapper\Utils\Url;

class ArrayStream extends AbstractStream
{
    use DirectoryIOTrait;
    use DirectoryIteratorTrait;
    use UrlIOTrait;
    use UrlPermissionTrait;
    use StreamTrait;

    protected static ArrayDriver $driver;

    /** @return array{ArrayDriver, string} */
    public static function resolve(string $url): array
    {
        $local   = new Url($url);
        $default = static::$default[$local->scheme];
        $merged  = $local->merge($default);

        $driver = static::$driver ??= new ArrayDriver('/', ['mode' => Stat::DIR | 0777]);

        return [$driver, preg_split('#/#', $merged->path, -1, PREG_SPLIT_NO_EMPTY)];
    }

    protected function parent(string $url, array $contextOptions): ?string
    {
        [, $pathes] = static::resolve($url);

        $dirname = dirname(implode('/', $pathes));
        if ($dirname === '.' || $dirname === '/' || $dirname === '\\') {
            return null;
        }
        $url            = new Url($url);
        $url->dirname   = "/$dirname";
        $url->filename  = '';
        $url->extension = '';
        return $url;
    }

    protected function children(string $url, array $contextOptions): iterable
    {
        [$driver, $pathes] = static::resolve($url);

        if ((new Stat($this->getMetadata($url) ?? []))->filetype() !== 'dir') {
            ErrorException::throwWarning("$url is not directory");
        }

        $node = $driver->child($pathes);
        foreach ($node as $key => $dummy) {
            yield "$url/$key";
        }
    }

    protected function move(string $src_url, string $dst_url, array $contextOptions): void
    {
        [$src_driver, $src_pathes] = static::resolve($src_url);
        [$dst_driver, $dst_pathes] = static::resolve($dst_url);

        $src = $src_driver->child($src_pathes);
        $dst = $dst_driver->parent($dst_pathes, $dst_name);

        $src->remove();
        $dst->append($src, $dst_name);
    }

    protected function getMetadata(string $url): ?array
    {
        [$driver, $pathes] = static::resolve($url);

        $node = ErrorException::suppress(fn() => $driver->child($pathes));
        if ($node === null) {
            return null;
        }

        $metadata         = $node->metadata;
        $metadata['size'] = strlen($node->contents);

        return $metadata;
    }

    protected function setMetadata(string $url, array $metadata): void
    {
        [$driver, $pathes] = static::resolve($url);

        unset($metadata['size']);
        $node           = $driver->child($pathes);
        $node->metadata = $metadata;
    }

    protected function createDirectory(string $url, int $permissions, array $contextOptions): void
    {
        [$driver, $pathes] = static::resolve($url);

        $node = $driver->parent($pathes, $name);
        $node->append(new ArrayDriver($name, ['mode' => Stat::DIR | $permissions]));
    }

    protected function deleteDirectory(string $url, array $contextOptions): void
    {
        [$driver, $pathes] = static::resolve($url);

        $node = $driver->child($pathes);
        $node->remove();
    }

    protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string
    {
        [$driver, $pathes] = static::resolve($url);

        $meta = $this->getMetadata($url);
        if ($meta === null || (new Stat($meta))->filetype() === 'dir') {
            ErrorException::throwWarning("$url is not found or directory");
        }

        $node             = $driver->child($pathes);
        $contents         = $node->contents;
        $metadata         = $node->metadata;
        $metadata['size'] = strlen($contents);

        return $contents;
    }

    protected function createFile(string $url, string $contents, array $metadata, array $contextOptions): void
    {
        [$driver, $pathes] = static::resolve($url);

        unset($metadata['size']);
        $node = $driver->parent($pathes, $name);
        $node->append(new ArrayDriver($name, $metadata, $contents));
    }

    protected function appendFile(string $url, string $contents, array $contextOptions): void
    {
        [$driver, $pathes] = static::resolve($url);

        $node           = $driver->child($pathes);
        $node->contents .= $contents;
    }

    protected function deleteFile(string $url, array $contextOptions): void
    {
        [$driver, $pathes] = static::resolve($url);

        $meta = $this->getMetadata($url);
        if ($meta === null || (new Stat($meta))->filetype() === 'dir') {
            ErrorException::throwWarning("$url is not found or directory");
        }

        $node = $driver->child($pathes);
        $node->remove();
    }
}

class ArrayDriver implements IteratorAggregate
{
    private string $name;
    private ?self  $parent;
    private array  $children;

    public array  $metadata;
    public string $contents;

    public function __construct(string $name, array $metadata, string $contents = '')
    {
        $this->name     = $name;
        $this->children = [];

        $this->metadata = $metadata;
        $this->contents = $contents;
    }

    public function append(self $child, ?string $name = null)
    {
        $child->parent = $this;
        $child->name   = $name ?? $child->name;

        $this->children[$child->name] = $child;
    }

    public function remove()
    {
        unset($this->parent->children[$this->name]);
    }

    public function parent(array $pathes, string &$name = null): self
    {
        $name = array_pop($pathes);
        return $this->child($pathes);
    }

    public function child(array $pathes): self
    {
        $route = [];
        $child = $this;
        foreach ($pathes as $path) {
            $route[] = $path;
            if (!isset($child->children[$path])) {
                ErrorException::throwWarning(implode('/', $route) . " is not found");
            }
            $child = $child->children[$path];
        }
        return $child;
    }

    public function getIterator()
    {
        yield from $this->children;
    }
}
