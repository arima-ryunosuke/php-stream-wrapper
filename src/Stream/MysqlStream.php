<?php
namespace ryunosuke\StreamWrapper\Stream;

use PDO;
use PDOException;
use PDOStatement;
use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\DirectoryIteratorTrait;
use ryunosuke\StreamWrapper\Mixin\StreamTrait;
use ryunosuke\StreamWrapper\Mixin\UrlIOTrait;
use ryunosuke\StreamWrapper\Mixin\UrlPermissionTrait;
use ryunosuke\StreamWrapper\Utils\Resource;
use ryunosuke\StreamWrapper\Utils\Url;

class MysqlStream extends AbstractStream
{
    use DirectoryIteratorTrait;
    use UrlIOTrait;
    use UrlPermissionTrait;
    use StreamTrait {
        StreamTrait::_flock as BufferTrait_flock;
    }

    protected static array $drivers = [];

    /** @return array{MysqlDriver, string, string} */
    public static function resolve(string $url, bool $noInitializeForTesting = false): array
    {
        $local   = new Url($url);
        $default = static::$default[$local->scheme];
        $merged  = $local->merge($default);

        $driver = static::$drivers[$merged->dsn] ??= (function () use ($merged) {
            return new MysqlDriver($merged->host, $merged->port, $merged->user, $merged->pass, $merged->query['charset'] ?? 'utf8mb4');
        })();

        $pathes    = preg_split('#/#', $default->path . $local->path, -1, PREG_SPLIT_NO_EMPTY);
        $tablename = array_shift($pathes) . '.' . array_shift($pathes);
        $id        = implode('/', $pathes);

        if (!$noInitializeForTesting) {
            $driver->initialize($tablename);
        }

        return [$driver, $tablename, $id];
    }

    protected function children(string $url, array $contextOptions): iterable
    {
        [$driver, $tablename, $id] = static::resolve($url);

        $id   = preg_replace('#([%_])#u', '\\\\$1', $id);
        $stmt = $driver->execute("SELECT REGEXP_SUBSTR(SUBSTR(id, LENGTH(:pattern)), '^[^/]+') FROM $tablename WHERE id LIKE :pattern GROUP BY 1", [
            'pattern' => ltrim("$id/%", '/'),
        ]);
        $stmt->setFetchMode(PDO::FETCH_COLUMN, 0);
        foreach ($stmt as $row) {
            yield "$url/$row";
        }
    }

    protected function move(string $src_url, string $dst_url, array $contextOptions): void
    {
        [$src_driver, $src_tablename, $src_id] = static::resolve($src_url);
        [$dst_driver, $dst_tablename, $dst_id] = static::resolve($dst_url);

        if ($src_driver !== $dst_driver) {
            $this->intermove($src_url, $dst_url, $contextOptions);
            return;
        }

        $cols = array_keys(array_filter(MysqlDriver::COLUMNS, fn($c) => $c['set'] !== null));
        $news = implode(', ', $cols);
        $olds = implode(', ', [':dstid'] + $cols);
        $src_driver->execute("REPLACE INTO $dst_tablename ($news) SELECT $olds FROM $src_tablename WHERE id = :srcid", [
            'dstid' => $dst_id,
            'srcid' => $src_id,
        ]);
        $src_driver->execute("DELETE FROM $src_tablename WHERE id = ?", [$src_id], false);
    }

    protected function getMetadata(string $url): ?array
    {
        [$driver, $tablename, $id] = static::resolve($url);

        [$columns] = $driver->selectColumns(true);
        $stmt   = $driver->execute("SELECT $columns FROM $tablename WHERE id = ?", [$id], false);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    protected function setMetadata(string $url, array $metadata): void
    {
        [$driver, $tablename, $id] = static::resolve($url);

        [$sets, $params] = $driver->updateColumns($metadata);
        $driver->execute("UPDATE $tablename SET $sets WHERE id = :id", ['id' => $id] + $params);
    }

    protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string
    {
        [$driver, $tablename, $id] = static::resolve($url);

        [$columns, $meta] = $driver->selectColumns(null);
        $stmt   = $driver->execute("SELECT $columns FROM $tablename WHERE id = ?", [$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $metadata = array_intersect_key($result, $meta);
        return $result['value'];
    }

    protected function createFile(string $url, string $value, array $metadata, array $contextOptions): void
    {
        [$driver, $tablename, $id] = static::resolve($url);

        [$sets, $values, $params] = $driver->insertColumns($metadata);
        $driver->execute("INSERT INTO $tablename SET $sets ON DUPLICATE KEY UPDATE $values", ['id' => $id, 'value' => $value] + $params);
    }

    protected function appendFile(string $url, string $contents, array $contextOptions): void
    {
        [$driver, $tablename, $id] = static::resolve($url);

        $driver->execute("UPDATE $tablename SET value = CONCAT(value, ?) WHERE id = ?", [$contents, $id]);
    }

    protected function deleteFile(string $url, array $contextOptions): void
    {
        [$driver, $tablename, $id] = static::resolve($url);

        $driver->execute("DELETE FROM $tablename WHERE id = ?", [$id]);
    }

    public function _flock(object/*Resource*/ $resource, int $operation): bool
    {
        /** @var Resource $resource */
        $this->BufferTrait_flock($resource, $operation);

        [$driver, $tablename, $id] = static::resolve($resource->url);

        if ($resource->locked) {
            if (!$driver->inTransaction()) {
                $driver->beginTransaction();
            }

            $lock = '';
            if ($resource->locked === LOCK_SH) {
                $lock = 'LOCK IN SHARE MODE';
            }
            if ($resource->locked === LOCK_EX) {
                $lock = 'FOR UPDATE';
            }
            $driver->execute("SELECT id FROM $tablename WHERE id = ? $lock", [$id], false);
        }
        else {
            if ($driver->inTransaction()) {
                $driver->commit();
            }
        }
        return true;
    }
}

/** @mixin PDO */
class MysqlDriver
{
    public const COLUMNS = [
        'id'    => [
            'meta'   => false,
            'create' => 'VARCHAR(256) NOT NULL COLLATE "utf8mb4_bin"',
            'get'    => 'id',
            'set'    => ':id',
        ],
        'value' => [
            'meta'   => false,
            'create' => 'LONGBLOB NOT NULL',
            'get'    => 'value',
            'set'    => ':value',
        ],
        'mode'  => [
            'meta'   => true,
            'create' => 'INT NOT NULL',
            'get'    => 'mode',
            'set'    => ':mode',
        ],
        'uid'   => [
            'meta'   => true,
            'create' => 'INT NOT NULL',
            'get'    => 'uid',
            'set'    => ':uid',
        ],
        'gid'   => [
            'meta'   => true,
            'create' => 'INT NOT NULL',
            'get'    => 'gid',
            'set'    => ':gid',
        ],
        'size'  => [
            'meta'   => true,
            'create' => null,
            'get'    => 'LENGTH(value)',
            'set'    => null,
        ],
        'mtime' => [
            'meta'   => true,
            'create' => 'DATETIME NOT NULL',
            'get'    => 'UNIX_TIMESTAMP(mtime)',
            'set'    => 'FROM_UNIXTIME(:mtime)',
        ],
        'ctime' => [
            'meta'   => true,
            'create' => 'DATETIME NOT NULL',
            'get'    => 'UNIX_TIMESTAMP(ctime)',
            'set'    => 'FROM_UNIXTIME(:ctime)',
        ],
    ];

    private array $ctor_args;
    private PDO   $pdo;
    private array $initialized = [];

    public function __construct(string $host, ?int $port, ?string $user, ?string $pass, $charset)
    {
        $this->ctor_args = [
            "mysql:host=$host;port=$port;charset=$charset",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
            ],
        ];
    }

    public function __call($name, $arguments)
    {
        $pdo = $this->pdo ??= new PDO(...$this->ctor_args);
        return $pdo->$name(...$arguments);
    }

    public function initialize(string $tablename)
    {
        if (!isset($this->initialized[$tablename])) {
            $this->initialized[$tablename] = true;

            $stmt = $this->execute("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", explode('.', $tablename), false);
            if ($stmt->rowCount() === 0) {
                $columns = array_filter(MysqlDriver::COLUMNS, fn($c) => $c['create'] !== null);
                $columns = array_combine(array_keys($columns), array_column($columns, 'create'));
                $column  = implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($columns), $columns));
                $this->execute("CREATE TABLE $tablename ($column, PRIMARY KEY (id))", [], false);
            }
        }
    }

    public function selectColumns(?bool $withMeta)
    {
        $columns = array_filter(MysqlDriver::COLUMNS, fn($c) => ($withMeta === null || $c['meta'] === $withMeta) && $c['get'] !== null);
        $columns = array_combine(array_keys($columns), array_column($columns, 'get'));
        return [
            implode(', ', array_map(fn($k, $v) => "$v AS $k", array_keys($columns), $columns)),
            array_filter(MysqlDriver::COLUMNS, fn($c) => $c['meta']),
        ];
    }

    public function insertColumns(array $metadata)
    {
        $columns = array_filter(MysqlDriver::COLUMNS, fn($c) => $c['set'] !== null);
        $columns = array_combine(array_keys($columns), array_column($columns, 'set'));
        return [
            implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($columns), $columns)),
            implode(', ', array_map(fn($k, $v) => "$k = VALUES($k)", array_keys($columns), $columns)),
            array_intersect_key($metadata, array_filter(MysqlDriver::COLUMNS, fn($c) => $c['set'] !== null)),
        ];
    }

    public function updateColumns(array $metadata)
    {
        $columns = array_filter(MysqlDriver::COLUMNS, fn($c) => $c['meta'] && $c['set'] !== null);
        $columns = array_combine(array_keys($columns), array_column($columns, 'set'));
        $columns = array_intersect_key($columns, $metadata);
        return [
            implode(', ', array_map(fn($k, $v) => "$k = $v", array_keys($columns), $columns)),
            array_intersect_key($metadata, array_filter(MysqlDriver::COLUMNS, fn($c) => $c['set'] !== null)),
        ];
    }

    public function execute(string $sql, array $params, bool $thrown = true): PDOStatement
    {
        $pdo = $this->pdo ??= new PDO(...$this->ctor_args);

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($thrown && $stmt->rowCount() === 0) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
                ErrorException::throwWarning("{$trace['function']} row count is 0");
            }
            return $stmt;
        }
        catch (PDOException $e) {
            ErrorException::throwWarning($e->getMessage(), $e); // @codeCoverageIgnore
        }
    }
}
