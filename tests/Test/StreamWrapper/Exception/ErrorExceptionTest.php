<?php

namespace ryunosuke\Test\StreamWrapper\Exception;

use Exception;
use LogicException;
use RuntimeException;
use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\Test\StreamWrapper\Stream\AbstractStreamTestCase;

class ErrorExceptionTest extends AbstractStreamTestCase
{
    function test_convert()
    {
        try {
            ErrorException::convert(function () {
                throw new RuntimeException();
            }, RuntimeException::class);
        }
        catch (Exception $e) {
            that(get_class($e))->is(ErrorException::class);
        }

        try {
            ErrorException::convert(function () {
                throw new RuntimeException();
            }, LogicException::class);
        }
        catch (Exception $e) {
            that(get_class($e))->is(RuntimeException::class);
        }
    }

    function test_suppress()
    {
        try {
            ErrorException::suppress(function () {
                ErrorException::throwDebug();
            }, 1);
        }
        catch (Exception $e) {
            that(get_class($e))->is(ErrorException::class);
        }

        try {
            $return = ErrorException::suppress(function () {
                ErrorException::throwWarning();
            }, 1);
            that($return)->is(1);
        }
        catch (Exception $e) {
            self::fail('not pass');
        }

        try {
            ErrorException::suppress(function () {
                throw new RuntimeException();
            }, 1);
        }
        catch (Exception $e) {
            that(get_class($e))->is(RuntimeException::class);
        }
    }

    function test_throw()
    {
        that(ErrorException::class)::throwNotice('hoge')->wasThrown(new ErrorException('hoge', E_USER_NOTICE));
        that(ErrorException::class)::throwWarning('fuga')->wasThrown(new ErrorException('fuga', E_USER_WARNING));
        that(ErrorException::class)::throwError('piyo')->wasThrown(new ErrorException('piyo', E_USER_ERROR));
    }

    function test_trigger()
    {
        $notice = new ErrorException('this is error message', E_USER_NOTICE);
        @$notice->trigger();
        that(error_get_last()['message'])->contains('this is error message');
    }

    function test_debug()
    {
        $old = ini_set('error_log', $errfile = tempnam(sys_get_temp_dir(), 'err'));
        file_put_contents($errfile, '');

        $debug = new ErrorException('this is debug message', 0);
        $debug->trigger();
        that($errfile)->fileContains('this is debug message');

        ini_set('error_log', $old);
    }
}
