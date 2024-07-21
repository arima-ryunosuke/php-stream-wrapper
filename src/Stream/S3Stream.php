<?php
namespace ryunosuke\StreamWrapper\Stream;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3ClientInterface;
use Aws\S3\S3MultiRegionClient;
use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\DirectoryIOTrait;
use ryunosuke\StreamWrapper\Mixin\DirectoryIteratorTrait;
use ryunosuke\StreamWrapper\Mixin\StreamTrait;
use ryunosuke\StreamWrapper\Mixin\UrlIOTrait;
use ryunosuke\StreamWrapper\Mixin\UrlPermissionTrait;
use ryunosuke\StreamWrapper\Utils\Stat;
use ryunosuke\StreamWrapper\Utils\Url;

class S3Stream extends AbstractStream
{
    use DirectoryIOTrait;
    use DirectoryIteratorTrait;
    use UrlIOTrait;
    use UrlPermissionTrait;
    use StreamTrait;

    protected static array $drivers = [];

    /** @return array{S3Driver, string, string} */
    public static function resolve(string $url): array
    {
        $local   = new Url($url);
        $default = static::$default[$local->scheme];
        $merged  = $local->merge($default);

        $driver = static::$drivers[$merged->dsn] ??= (function () use ($merged) {
            $customarray = [];
            if (strlen($merged->userpass)) {
                $customarray['credentials'] = [
                    'key'    => $merged->user,
                    'secret' => $merged->pass,
                ];
            }
            if (strlen($merged->hostport)) {
                $customarray['endpoint'] = (($merged->query['secure'] ?? false) ? 'https' : 'http') . "://{$merged->hostport}";
            }
            if (isset($merged->query['use_path_style_endpoint'])) {
                $customarray['use_path_style_endpoint'] = filter_var($merged->query['use_path_style_endpoint'], FILTER_VALIDATE_BOOLEAN);
            }

            return new S3Driver(array_replace($customarray, [
                'region'  => $merged->query['region'] ?? 'ap-northeast-1',
                'version' => $merged->query['version'] ?? 'latest',
            ]));
        })();

        $pathes = preg_split('#/#', $default->path . $local->path, -1, PREG_SPLIT_NO_EMPTY);
        $bucket = array_shift($pathes);
        $key    = implode('/', $pathes);

        return [$driver, $bucket, $key, !!strlen($default->path ?? '')];
    }

    protected function parent(string $url, array $contextOptions): ?string
    {
        [, $bucket, $key, $defaultBucket] = static::resolve($url);

        $dirname = dirname($key);
        if ($dirname === '.' || $dirname === '/' || $dirname === '\\') {
            return null;
        }
        $url            = new Url($url);
        $url->dirname   = ($defaultBucket ? "" : "/$bucket") . "/$dirname";
        $url->filename  = '';
        $url->extension = '';
        return $url;
    }

    protected function children(string $url, array $contextOptions): iterable
    {
        [$driver, $bucket, $key] = static::resolve($url);

        $result = $driver->getPaginator('ListObjects', [
            'Bucket'    => $bucket,
            'Prefix'    => ltrim(rtrim($key, '/') . '/', '/'),
            'Delimiter' => "/",
        ]);
        foreach ($result as $n => $objects) {
            $keys = array_merge($objects['CommonPrefixes'] ?? [], $objects['Contents'] ?? []);
            if ($n === 0 && count($keys) === 0) {
                ErrorException::throwWarning("$url: is not found");
            }
            yield from array_map(fn($v) => "$url/$v", $this->iterableFilterLocalPath($key, $keys, fn($v) => $v['Prefix'] ?? $v['Key']));
        }
    }

    protected function move(string $src_url, string $dst_url, array $contextOptions): void
    {
        [$src_driver, $src_bucket, $src_key] = static::resolve($src_url);
        [$dst_driver, $dst_bucket, $dst_key] = static::resolve($dst_url);

        if ($src_driver !== $dst_driver) {
            $this->intermove($src_url, $dst_url, $contextOptions);
            return;
        }

        $src_driver->execute('CopyObject', [
            'Bucket'     => $dst_bucket,
            'Key'        => $dst_key,
            'CopySource' => "$src_bucket/$src_key",
        ]);
        $this->deleteFile($src_url, $contextOptions);
    }

    protected function getMetadata(string $url): ?array
    {
        [$driver, $bucket, $key] = static::resolve($url);

        // bucket is definitive directory
        if ($key === '') {
            return ['mode' => Stat::DIR | 0777];
        }

        $object = $driver->execute('HeadObject', [
            'Bucket' => $bucket,
            'Key'    => $key,
        ], false);
        $object ??= $driver->execute('HeadObject', [
            'Bucket' => $bucket,
            'Key'    => "$key/",
        ], false);
        if ($object === null) {
            return null;
        }

        return array_replace($object['Metadata'], [
            'size'  => $object['ContentLength'],
            'mtime' => $object['LastModified']->getTimestamp(),
        ]);
    }

    protected function setMetadata(string $url, array $metadata): void
    {
        [$driver, $bucket, $key] = static::resolve($url);

        $driver->execute('CopyObject', [
            'Bucket'            => $bucket,
            'Key'               => $key,
            'CopySource'        => "$bucket/$key",
            'MetadataDirective' => 'REPLACE',
            'Metadata'          => $metadata,
        ]);
    }

    protected function createDirectory(string $url, int $permissions, array $contextOptions): void
    {
        [$driver, $bucket, $key] = static::resolve($url);

        $driver->execute('PutObject', [
            'Bucket'   => $bucket,
            'Key'      => rtrim($key, '/') . '/',
            'Body'     => '',
            'Metadata' => ['mode' => Stat::DIR | $permissions],
        ]);
    }

    protected function deleteDirectory(string $url, array $contextOptions): void
    {
        [$driver, $bucket, $key] = static::resolve($url);

        $driver->execute('DeleteObject', [
            'Bucket' => $bucket,
            'Key'    => rtrim($key, '/') . '/',
        ]);
    }

    protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string
    {
        [$driver, $bucket, $key] = static::resolve($url);

        $object = $driver->execute('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);

        $metadata = array_replace($object['Metadata'], [
            'size'  => $object['ContentLength'],
            'mtime' => $object['LastModified']->getTimestamp(),
        ]);

        return $object['Body']->getContents();
    }

    protected function createFile(string $url, string $value, array $metadata, array $contextOptions): void
    {
        [$driver, $bucket, $key] = static::resolve($url);

        unset($metadata['size']);
        unset($metadata['mtime']);

        $driver->execute('PutObject', [
            'Bucket'   => $bucket,
            'Key'      => $key,
            'Body'     => $value,
            'Metadata' => $metadata,
        ]);
    }

    protected function appendFile(string $url, string $contents, array $contextOptions): void
    {
        $contents = $this->selectFile($url, $metadata, $contextOptions) . $contents;
        $this->createFile($url, $contents, $metadata, $contextOptions);
    }

    protected function deleteFile(string $url, array $contextOptions): void
    {
        [$driver, $bucket, $key] = static::resolve($url);

        // it is meaningless to check the existence of the link since it will be deleted anyway.
        // but it is necessary to check it in order to set the return value of unlink to false.
        if ($this->getMetadata($url) === null) {
            ErrorException::throwWarning("$url: is not found");
        }

        $driver->execute('DeleteObject', [
            'Bucket' => $bucket,
            'Key'    => $key,
        ]);
    }
}

/** @mixin S3ClientInterface */
class S3Driver
{
    private S3ClientInterface $client;

    public function __construct(array $args)
    {
        $this->client = new S3MultiRegionClient($args);
    }

    public function __call($name, $arguments)
    {
        return $this->client->$name(...$arguments);
    }

    public function execute(string $command, array $args, bool $thrown = true)
    {
        try {
            return $this->client->$command($args);
        }
        catch (S3Exception $e) {
            if ($thrown) {
                ErrorException::throwWarning($e->getMessage(), $e); // @codeCoverageIgnore
            }
            return null;
        }
    }

    /** @codeCoverageIgnore this is for testing */
    public function refreshBucket(string $bucket)
    {
        if ($this->client->doesBucketExist($bucket)) {
            $objects = $this->client->listObjects([
                'Bucket' => $bucket,
                'Prefix' => '',
            ])['Contents'];
            foreach ((array) $objects as $object) {
                $this->client->deleteObject([
                    'Bucket' => $bucket,
                    'Key'    => $object['Key'],
                ]);
            }
        }
        else {
            $this->client->createBucket([
                'Bucket' => $bucket,
            ]);
        }
    }
}
