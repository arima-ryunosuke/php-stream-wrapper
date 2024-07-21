<?php
namespace ryunosuke\StreamWrapper\Utils;

use InvalidArgumentException;
use LogicException;

/**
 * @property ?string $scheme
 * @property ?string $user
 * @property ?string $pass
 * @property ?string $host
 * @property ?int $port
 * @property ?string $dirname
 * @property ?string $filename
 * @property ?string $extension
 * @property ?array $query
 * @property ?string $fragment
 *
 * @property-read string $url fullurl
 * @property-read string $userpass user+pass
 * @property-read string $hostport host+port
 * @property-read string $authority user+pass+host+port
 * @property-read ?string $path dirname+filename+extension
 * @property-read string $basename filename+extension
 * @property-read string $querystring http_build_query(query)
 * @property-read string $dsn user+pass+host+port+querystring
 */
class Url
{
    private bool $localProtocol;

    private ?string $scheme;
    private ?string $user;
    private ?string $pass;
    private ?string $host;
    private ?int    $port;
    private ?string $dirname;
    private ?string $filename;
    private ?string $extension;
    private ?array  $query;
    private ?string $fragment;

    public function __construct(string $url, bool $localProtocol = false)
    {
        $this->localProtocol = $localProtocol;

        if ($this->localProtocol) {
            $url = preg_replace('#^[a-z][-+.0-9a-z]*://#', '$0localhost/', $url);
        }

        $url = strtr($url, ['\\' => '/']);
        $url = preg_replace('#^//#', '', $url);

        if (!preg_match(<<<REGEXP
            #^
                ((?<scheme>[a-z][-+.0-9a-z]*)://)?
                (
                    (?<user>([-.~\\w] | %[0-9a-f][0-9a-f] | [!$&-,;=])*)?
                  (:(?<pass>([-.~\\w] | %[0-9a-f][0-9a-f] | [!$&-,;=])*))?@
                )?
                (?<host>((\\[[0-9a-f:]+\\]) | ([-.~0-9a-z]|%[0-9a-f][0-9a-f]|[!$&-,;=]))+)?
                (:(?<port>\d{1,5}))?
                (?<path>(/( [-.~\\w!$&'()*+,;=:@] | %[0-9a-f]{2} )* )+)?
                (\\?(?<query>[^\\#]*))?
                (\\#(?<fragment>.*))?
            \$#ix
            REGEXP, $url, $matches, PREG_UNMATCHED_AS_NULL)) {
            throw new InvalidArgumentException("'$url' is malformed URL");
        }

        $this->scheme = $matches['scheme'];

        $this->user = $matches['user'] === null ? null : rawurldecode($matches['user']);
        $this->pass = $matches['pass'] === null ? null : rawurldecode($matches['pass']);

        $this->host = $matches['host'] === null ? null : preg_replace('#^\\[(.+)]$#', '$1', $matches['host']);
        $this->port = $matches['port'] === null ? null : (int) $matches['port'];

        if ($matches['path'] === null) {
            $this->dirname   = null;
            $this->filename  = null;
            $this->extension = null;
        }
        else {
            $pathinfo        = pathinfo(rawurldecode('/' . ltrim($matches['path'], '/')));
            $this->dirname   = $pathinfo['dirname'] === '.' ? '' : '/' . ltrim(strtr($pathinfo['dirname'], '\\', '/'), '/');
            $this->filename  = $pathinfo['filename'];
            $this->extension = $pathinfo['extension'] ?? '';
        }

        $this->query    = $matches['query'] === null ? null : $this->parseQuery($matches['query']);
        $this->fragment = $matches['fragment'] === null ? null : rawurldecode($matches['fragment']);
    }

    public function __get($name)
    {
        $E = fn($v) => $v;

        switch ($name) {
            case 'url':
                return implode('', [
                    $this->scheme === null ? '' : "{$this->scheme}://",
                    $this->localProtocol ? '' : $this->authority,
                    $this->localProtocol ? ltrim($this->path, '/') : $this->path,
                    $this->querystring,
                    $this->fragment === null ? '' : "#{$E(rawurlencode($this->fragment))}",
                ]);
            case 'authority':
                return implode('', [
                    $this->userpass,
                    strlen($this->userpass) ? '@' : '',
                    $this->hostport,
                ]);
            case 'userpass':
                return implode('', [
                    $this->user === null ? '' : "{$E(rawurlencode($this->user))}",
                    $this->pass === null ? '' : ":{$E(rawurlencode($this->pass))}",
                ]);
            case 'hostport':
                return implode('', [
                    $this->host === null ? '' : "{$this->host}",
                    $this->port === null ? '' : ":{$this->port}",
                ]);
            case 'dsn':
                return implode('', [
                    $this->authority,
                    $this->querystring,
                ]);
            case 'path':
                return $this->dirname === null ? null : '/' . ltrim(implode('', [
                        strlen($this->dirname) ? rtrim($this->dirname, '/') : "",
                        strlen($this->basename) ? "/{$this->basename}" : "",
                    ]), '/');
            case 'basename':
                return implode('', [
                    $this->filename,
                    strlen($this->extension) ? ".$this->extension" : "",
                ]);
            case 'querystring':
                return $this->query === null ? '' : "?{$E(http_build_query($this->query))}";
            default:
                assert(property_exists($this, $name));
                return $this->$name;
        }
    }

    public function __set($name, $value)
    {
        assert(property_exists($this, $name));
        $this->$name = $value;
    }

    public function __toString(): string
    {
        return $this->url;
    }

    public function array(): array
    {
        return [
            'scheme'    => $this->scheme,
            'user'      => $this->user,
            'pass'      => $this->pass,
            'host'      => $this->host,
            'port'      => $this->port,
            'dirname'   => $this->dirname,
            'filename'  => $this->filename,
            'extension' => $this->extension,
            'query'     => $this->query,
            'fragment'  => $this->fragment,
        ];
    }

    public function merge(self $that): self
    {
        if ($this->scheme !== $that->scheme) {
            throw new LogicException("mismatch scheme");
        }

        $result            = clone $this;
        $result->user      ??= $that->user;
        $result->pass      ??= $that->pass;
        $result->host      ??= $that->host;
        $result->port      ??= $that->port;
        $result->dirname   ??= $that->dirname;
        $result->filename  ??= $that->filename;
        $result->extension ??= $that->extension;
        $result->fragment  ??= $that->fragment;

        if (isset($that->query)) {
            $result->query = array_replace_recursive($this->query ?? [], $that->query);
        }

        return $result;
    }

    private function parseQuery(string $query): array
    {
        //parse_str($query, $result);
        //return $result;

        $result = [];
        foreach (explode('&', $query) as $param) {
            [$name, $value] = explode("=", trim($param), 2) + [1 => ''];
            if ($name === '') {
                continue;
            }
            $name  = rawurldecode($name);
            $value = rawurldecode($value);

            if (preg_match_all('#\[([^]]*)\]#mu', $name, $matches, PREG_OFFSET_CAPTURE)) {
                $name = substr($name, 0, $matches[0][0][1]);
                $keys = array_column($matches[1], 0);

                $receiver = &$result[$name];
                foreach ($keys as $key) {
                    if (strlen($key) === 0) {
                        if (!is_array($receiver)) {
                            $receiver = [];
                        }
                        $key = max(array_filter(array_keys($receiver), 'is_int') ?: [-1]) + 1;
                    }
                    $receiver = &$receiver[$key];
                }

                $receiver = $value;
                unset($receiver);
            }
            else {
                $result[$name] = $value;
            }
        }
        return $result;
    }
}
