<?php

namespace ryunosuke\Test\StreamWrapper\Utils;

use ryunosuke\StreamWrapper\Utils\Url;
use ryunosuke\Test\StreamWrapper\Stream\AbstractStreamTestCase;

class UrlTest extends AbstractStreamTestCase
{
    public static function provideUrl()
    {
        return [
            [
                '',
                [
                    'scheme'   => null,
                    'user'     => null,
                    'pass'     => null,
                    'host'     => null,
                    'port'     => null,
                    'path'     => null,
                    'query'    => null,
                    'fragment' => null,
                ],
            ],
            [
                'host',
                [
                    'host' => 'host',
                ],
            ],
            [
                'user@host',
                [
                    'user' => 'user',
                    'pass' => null,
                    'host' => 'host',
                    'port' => null,
                ],
            ],
            [
                'user:pass@host',
                [
                    'user' => 'user',
                    'pass' => 'pass',
                    'host' => 'host',
                    'port' => null,
                ],
            ],
            [
                'user:@host',
                [
                    'user' => 'user',
                    'pass' => '',
                    'host' => 'host',
                    'port' => null,
                ],
            ],
            [
                ':pass@host',
                [
                    'user' => '',
                    'pass' => 'pass',
                    'host' => 'host',
                    'port' => null,
                ],
            ],
            [
                ':@host',
                [
                    'user' => '',
                    'pass' => '',
                    'host' => 'host',
                    'port' => null,
                ],
            ],
            [
                'host:123',
                [
                    'host' => 'host',
                    'port' => 123,
                ],
            ],
            [
                'user:pass@host:123',
                [
                    'user' => 'user',
                    'pass' => 'pass',
                    'host' => 'host',
                    'port' => 123,
                ],
            ],
            [
                'host/path/to/file',
                [
                    'host' => 'host',
                    'path' => '/path/to/file',
                ],
            ],
            [
                'host?a=A&b=B',
                [
                    'host'  => 'host',
                    'query' => ['a' => 'A', 'b' => 'B'],
                ],
            ],
            [
                'host/path/to/file?a=A&b=B',
                [
                    'host'  => 'host',
                    'path'  => '/path/to/file',
                    'query' => ['a' => 'A', 'b' => 'B'],
                ],
            ],
            [
                'host#fragment',
                [
                    'host'     => 'host',
                    'fragment' => 'fragment',
                ],
            ],
            [
                'host?a=A&b=B#fragment',
                [
                    'host'     => 'host',
                    'query'    => ['a' => 'A', 'b' => 'B'],
                    'fragment' => 'fragment',
                ],
            ],
            [
                'host/?a=A&b=B#fragment',
                [
                    'host'     => 'host',
                    'path'     => '/',
                    'query'    => ['a' => 'A', 'b' => 'B'],
                    'fragment' => 'fragment',
                ],
            ],
            [
                'scheme:///path/to/file',
                [
                    'scheme' => 'scheme',
                    'host'   => null,
                    'path'   => '/path/to/file',
                ],
            ],
            [
                'scheme://user:pass@example.com:123/path/to/file?a=A&b=B#fragment',
                [
                    'scheme'   => 'scheme',
                    'user'     => 'user',
                    'pass'     => 'pass',
                    'host'     => 'example.com',
                    'port'     => 123,
                    'path'     => '/path/to/file',
                    'query'    => ['a' => 'A', 'b' => 'B'],
                    'fragment' => 'fragment',
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideUrl
     */
    function test_all($url, $parts)
    {
        $urlobject = new Url($url);
        that($urlobject)->scheme->isSame($parts['scheme'] ?? null);
        that($urlobject)->user->isSame($parts['user'] ?? null);
        that($urlobject)->pass->isSame($parts['pass'] ?? null);
        that($urlobject)->host->isSame($parts['host'] ?? null);
        that($urlobject)->port->isSame($parts['port'] ?? null);
        that($urlobject)->path->isSame($parts['path'] ?? null);
        that($urlobject)->query->isSame($parts['query'] ?? null);
        that($urlobject)->fragment->isSame($parts['fragment'] ?? null);
        that((string) $urlobject)->isSame($url);
    }

    function test_new()
    {
        that(Url::class)->new('.%/.a')->wasThrown('malformed URL');
        that(Url::class)->new('scheme://_:::/')->wasThrown('malformed URL');
        that(Url::class)->new('scheme://_')->wasThrown('malformed URL');
        that(Url::class)->new('scheme://a:')->wasThrown('malformed URL');
        that(Url::class)->new('scheme://a/%a')->wasThrown('malformed URL');
    }

    function test_getset()
    {
        $url = new Url('scheme://user:pass@host:80/path?a=1#fragment');

        // actual
        that($url)->__get('scheme')->isSame('scheme');
        that($url)->__get('user')->isSame('user');
        that($url)->__get('pass')->isSame('pass');
        that($url)->__get('host')->isSame('host');
        that($url)->__get('port')->isSame(80);
        that($url)->__get('path')->isSame('/path');
        that($url)->__get('query')->isSame(['a' => '1']);
        that($url)->__get('fragment')->isSame('fragment');

        // virtual
        that($url)->__get('url')->isSame('scheme://user:pass@host:80/path?a=1#fragment');
        that($url)->__get('userpass')->isSame('user:pass');
        that($url)->__get('hostport')->isSame('host:80');
        that($url)->__get('authority')->isSame('user:pass@host:80');
        that($url)->__get('dsn')->isSame('user:pass@host:80?a=1');

        // set
        $url->__set('pass', '');
        $url->__set('port', null);
        $url->__set('query', ['a' => 2]);

        // reflect
        that($url)->__get('url')->isSame('scheme://user:@host/path?a=2#fragment');
        that($url)->__get('userpass')->isSame('user:');
        that($url)->__get('hostport')->isSame('host');
        that($url)->__get('authority')->isSame('user:@host');
        that($url)->__get('dsn')->isSame('user:@host?a=2');
    }

    function test_merge()
    {
        that(new Url('scheme1://'))->merge(new Url('scheme2://'))->wasThrown('mismatch');

        $url0 = new Url('scheme://');
        $url1 = new Url('scheme://user1:pass1@host1:81/path1?a=1#fragment1');
        $url2 = new Url('scheme://user2:pass2@host2:82/path2?a=2&b=2#fragment2');

        that($url1->merge($url0))->array()->is([
            "scheme"   => "scheme",
            "user"     => "user1",
            "pass"     => "pass1",
            "host"     => "host1",
            "port"     => 81,
            "path"     => "/path1",
            "query"    => [
                "a" => "1",
            ],
            "fragment" => "fragment1",
        ]);
        that($url0->merge($url1))->array()->is([
            "scheme"   => "scheme",
            "user"     => "user1",
            "pass"     => "pass1",
            "host"     => "host1",
            "port"     => 81,
            "path"     => "/path1",
            "query"    => [
                "a" => "1",
            ],
            "fragment" => "fragment1",
        ]);
        that($url0->merge($url2))->array()->is([
            "scheme"   => "scheme",
            "user"     => "user2",
            "pass"     => "pass2",
            "host"     => "host2",
            "port"     => 82,
            "path"     => "/path2",
            "query"    => [
                "a" => "2",
                "b" => "2",
            ],
            "fragment" => "fragment2",
        ]);
        that($url1->merge($url2))->array()->is([
            "scheme"   => "scheme",
            "user"     => "user1",
            "pass"     => "pass1",
            "host"     => "host1",
            "port"     => 81,
            "path"     => "/path1",
            "query"    => [
                "a" => "2",
                "b" => "2",
            ],
            "fragment" => "fragment1",
        ]);
        that($url2->merge($url1))->array()->is([
            "scheme"   => "scheme",
            "user"     => "user2",
            "pass"     => "pass2",
            "host"     => "host2",
            "port"     => 82,
            "path"     => "/path2",
            "query"    => [
                "a" => "1",
                "b" => "2",
            ],
            "fragment" => "fragment2",
        ]);
    }

    function test_parseQuery()
    {
        $url = new Url('scheme://');

        $query = implode('&', [
            'single=1',
            'multiple[]=1',
            'multiple[]=2',
            'same2[]=1',
            'same1=1',
            'same1=2',
            'same1[]=3',
            'same2=2',
            'same2=3',
            'nest[key1][key2][]=123',
            'nest[key1][key2][]=456',
            'nameonly',
            '=valueonly',
            'foo[bar]baz=invalid',
            '&&&&&',
        ]);
        parse_str($query, $expected);
        that($url)->parseQuery($query)->is($expected);

        $query = implode('&', [
            'plusmark=+',
            'atmark=@',
            '%40mark1=@',
            'dot.name=1',
            'hyphen-name=1',
            'space name1=1',
            'space%20name2=2',
        ]);
        that($url)->parseQuery($query)->is([
            "plusmark"    => "+",
            "atmark"      => "@",
            "@mark1"      => "@",
            "dot.name"    => "1",
            "hyphen-name" => "1",
            "space name1" => "1",
            "space name2" => "2",
        ]);
    }
}
