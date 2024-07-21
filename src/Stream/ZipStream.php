<?php
namespace ryunosuke\StreamWrapper\Stream;

use DomainException;
use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\DirectoryIOTrait;
use ryunosuke\StreamWrapper\Mixin\DirectoryIteratorTrait;
use ryunosuke\StreamWrapper\Mixin\StreamTrait;
use ryunosuke\StreamWrapper\Mixin\UrlIOTrait;
use ryunosuke\StreamWrapper\Utils\Stat;
use ryunosuke\StreamWrapper\Utils\Url;
use ZipArchive;

class ZipStream extends AbstractStream
{
    use DirectoryIOTrait;
    use DirectoryIteratorTrait;
    use UrlIOTrait;
    use StreamTrait;

    protected static array $drivers = [];

    /** @return array{ZipDriver, string, bool} */
    public static function resolve(string $url): array
    {
        $local   = new Url($url);
        $default = static::$default[$local->scheme];
        $merged  = $local->merge($default);

        $pathes = preg_split('#/#', $merged->path, -1, PREG_SPLIT_NO_EMPTY);
        if (strlen($merged->fragment ?? '')) {
            $fragmentMode = true;
            $zipfilename  = implode('/', $pathes);
            $zipitemname  = $merged->fragment;
        }
        else {
            $fragmentMode = false;
            foreach ($pathes as $n => $path) {
                if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
                    $zipfilename = implode('/', array_slice($pathes, 0, $n + 1));
                    $zipitemname = implode('/', array_slice($pathes, $n + 1));
                    break;
                }
            }
        }

        if (!isset($zipfilename) || !isset($zipitemname)) {
            throw new DomainException("can't detect zip file path($url)");
        }

        $zipfilename = DIRECTORY_SEPARATOR === '/' ? "/$zipfilename" : $zipfilename;

        $driver = static::$drivers[$zipfilename] ??= (function () use ($merged, $zipfilename) {
            return new ZipDriver($zipfilename, $merged->pass);
        })();
        return [$driver->open(), $zipitemname, $zipfilename, $fragmentMode];
    }

    protected function parent(string $url, array $contextOptions): ?string
    {
        [, $name, $zipfilename, $fragmentMode] = static::resolve($url);

        $dirname = dirname($name);
        if ($dirname === '.' || $dirname === '/' || $dirname === '\\') {
            return null;
        }
        $url = new Url($url);
        if ($fragmentMode) {
            $url->fragment = "$dirname/";
        }
        else {
            $url->dirname   = "/$zipfilename/$dirname/";
            $url->filename  = '';
            $url->extension = '';
        }
        return $url;
    }

    protected function children(string $url, array $contextOptions): iterable
    {
        [$driver, $name] = static::resolve($url);

        if ((new Stat($this->getMetadata($url) ?? []))->filetype() !== 'dir') {
            ErrorException::throwWarning($driver->getStatusString());
        }

        $result = [];
        foreach ($driver->iterate() as $item) {
            if (strpos($item, "$name/") !== false) {
                $result[] = $item;
            }
        }
        return array_map(fn($v) => "$url/$v", $this->iterableFilterLocalPath($name, $result));
    }

    protected function move(string $src_url, string $dst_url, array $contextOptions): void
    {
        [$src_driver, $src_name] = static::resolve($src_url);
        [$dst_driver, $dst_name] = static::resolve($dst_url);

        if ($src_driver !== $dst_driver) {
            $this->intermove($src_url, $dst_url, $contextOptions);
            return;
        }

        $src_driver->locateName($src_name);

        ErrorException::suppress(fn() => $src_driver->deleteName($dst_name));
        $src_driver->renameName($src_name, $dst_name);
    }

    protected function getMetadata(string $url): ?array
    {
        [$driver, $name] = static::resolve($url);

        // self is definitive directory
        if (ltrim($name, '/') === '') {
            return (new Stat(['mode' => Stat::DIR | 0777]))->array();
        }

        $stat = ErrorException::suppress(fn() => $driver->statName($name));
        if ($stat === null) {
            $name = "$name/";
            $stat = ErrorException::suppress(fn() => $driver->statName($name));
        }

        // no error and tailing slash is directory
        if ($stat && ($name[-1] ?? null) === '/') {
            $stat['mode'] = Stat::DIR | 0777;
        }

        return $stat;
    }

    protected function setMetadata(string $url, array $metadata): void
    {
        [$driver, $name] = static::resolve($url);

        $driver->setMtimeName($name, $metadata['mtime']);
    }

    protected function createDirectory(string $url, int $permissions, array $contextOptions): void
    {
        [$driver, $name] = static::resolve($url);

        $driver->addEmptyDir(rtrim($name, '/') . '/');
    }

    protected function deleteDirectory(string $url, array $contextOptions): void
    {
        [$driver, $name] = static::resolve($url);

        $driver->deleteName(rtrim($name, '/') . '/');
    }

    protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string
    {
        [$driver, $name] = static::resolve($url);

        $metadata = $driver->statName($name);
        $contents = $driver->getFromName($name);

        return $contents;
    }

    protected function createFile(string $url, string $contents, array $metadata, array $contextOptions): void
    {
        [$driver, $name] = static::resolve($url);

        foreach ($this->parents($url, $contextOptions) as $purl => $parent) {
            if ($parent !== 'dir') {
                ErrorException::throwWarning("$purl: is not directory");
            }
            break;
        }

        $driver->addFromString($name, $contents);
        $driver->setMtimeName($name, $metadata['mtime']);
    }

    protected function appendFile(string $url, string $contents, array $contextOptions): void
    {
        /** for now, it only supports read operations.
         * [$driver, $name] = static::resolve($url);
         * $stream = $driver->getStream($name, 'a');
         * fwrite($stream, $contents);
         */

        $contents = $this->selectFile($url, $metadata, $contextOptions) . $contents;
        $this->createFile($url, $contents, $metadata, $contextOptions);
    }

    protected function deleteFile(string $url, array $contextOptions): void
    {
        [$driver, $name] = static::resolve($url);

        $driver->deleteName($name);
    }
}

/** @mixin ZipArchive */
class ZipDriver
{
    private ZipArchive $archive;
    private string     $filename;
    private string     $password;
    private bool       $dirty;

    public function __construct(string $filename, ?string $password)
    {
        $this->archive  = new ZipArchive();
        $this->filename = DIRECTORY_SEPARATOR === '/' ? $filename : ltrim($filename, '/');
        $this->password = $password ?? '';
    }

    public function __call($name, $arguments)
    {
        $result = $this->archive->$name(...$arguments);
        if ($result === false) {
            ErrorException::throwWarning($this->archive->getStatusString());
        }
        return $result;
    }

    public function open()
    {
        if ($this->archive->filename === '') {
            if (($ret = $this->archive->open($this->filename, ZipArchive::CREATE)) !== true) {
                ErrorException::throwWarning("failed to open {$this->filename}(err $ret)");
            }
            if (strlen($this->password)) {
                $this->archive->setPassword($this->password);
            }
            $this->dirty = false;
        }
        return $this;
    }

    public function iterate()
    {
        for ($i = 0; $i < $this->archive->count(); $i++) {
            $name = $this->archive->getNameIndex($i);
            if ($name !== false) {
                yield $i => $name;
            }
        }
    }

    public function getFromName($name)
    {
        // reopen because addFromString is buffering(getFromName returns false after that).
        if ($this->dirty) {
            $this->archive->close();
            $this->open();
        }
        return $this->archive->getFromName($name);
    }

    public function addFromString($name, $content)
    {
        $result = $this->archive->addFromString($name, $content);

        if (strlen($content)) {
            $this->dirty = true;
        }
        return $result;
    }

    public function setMtimeName($name, $timestamp)
    {
        if (version_compare(PHP_VERSION, 8) >= 0) {
            return $this->archive->setMtimeName($name, $timestamp); // @codeCoverageIgnore
        }
        return true;
    }
}
