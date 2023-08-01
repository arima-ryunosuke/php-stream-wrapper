<?php

namespace ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait;

use ryunosuke\Test\StreamWrapper\Stream\FileStreamTest;

trait UrlPermissionTest
{
    function test_coverage_for_windows()
    {
        if (!($this instanceof FileStreamTest)) {
            return;
        }

        $url = static::$scheme0 . "://" . static::$namespace . "/windows";

        touch($url);
        umask(0);

        that(@chmod($url, 0777))->is(true);
        that(@chown($url, 27))->is(static::isRoot()); // On Windows, this function fails silently when applied on a regular file.
        that(@chgrp($url, 48))->is(static::isRoot()); // On Windows, this function fails silently when applied on a regular file.
    }

    function test_chmod()
    {
        static::isPosixOrSkip(false);

        $url = static::$scheme0 . "://" . static::$namespace . "/permission";

        @unlink($url);
        that(@chmod($url, 0777))->is(false);

        touch($url);
        umask(0);

        that(chmod($url, 0777))->is(true);
        that(fileperms($url))->is(010_0777);
    }

    function test_chown()
    {
        static::isPosixOrSkip(true);

        $url = static::$scheme0 . "://" . static::$namespace . "/permission";

        @unlink($url);
        that(@chown($url, 27))->is(false);

        touch($url);

        that(chown($url, 27))->is(true);
        that(fileowner($url))->is(27);
    }

    function test_chgrp()
    {
        static::isPosixOrSkip(true);

        $url = static::$scheme0 . "://" . static::$namespace . "/permission";

        @unlink($url);
        that(@chgrp($url, 48))->is(false);

        touch($url);

        that(chgrp($url, 48))->is(true);
        that(filegroup($url))->is(48);
    }

    function test_readable()
    {
        static::isPosixOrSkip(false);

        $url = static::$scheme0 . "://" . static::$namespace . "/permission";

        touch($url);
        chmod($url, 0555);

        that(@fopen($url, 'r'))->isType(static::isRoot() ? 'resource' : 'resource');
        that(@fopen($url, 'w'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'c'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'a'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'r+'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'w+'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'c+'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'a+'))->isType(static::isRoot() ? 'resource' : 'bool');
    }

    function test_writable()
    {
        static::isPosixOrSkip(false);

        $url = static::$scheme0 . "://" . static::$namespace . "/permission";

        touch($url);
        chmod($url, 0333);

        that(@fopen($url, 'r'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'w'))->isType(static::isRoot() ? 'resource' : 'resource');
        that(@fopen($url, 'c'))->isType(static::isRoot() ? 'resource' : 'resource');
        that(@fopen($url, 'a'))->isType(static::isRoot() ? 'resource' : 'resource');
        that(@fopen($url, 'r+'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'w+'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'c+'))->isType(static::isRoot() ? 'resource' : 'bool');
        that(@fopen($url, 'a+'))->isType(static::isRoot() ? 'resource' : 'bool');
    }
}
