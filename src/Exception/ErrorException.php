<?php
namespace ryunosuke\StreamWrapper\Exception;

use Closure;
use Exception;
use RuntimeException;
use Throwable;

class ErrorException extends RuntimeException
{
    public static function convert(Closure $callback, string $catch = Exception::class, int $code = E_USER_WARNING)
    {
        try {
            return $callback();
        }
        catch (Throwable $t) {
            if ($t instanceof $catch) {
                throw new static($t->getMessage(), $code, $t);
            }
            throw $t;
        }
    }

    public static function suppress(Closure $callback, $default = null, ?int $code = E_USER_WARNING)
    {
        try {
            return $callback();
        }
        catch (ErrorException $e) {
            if ($code === null || $e->getCode() === $code) {
                return $default;
            }
            throw $e;
        }
    }

    public static function throwDebug(string $message = "", Throwable $previous = null)/*: never*/
    {
        throw new static($message, 0, $previous);
    }

    public static function throwNotice(string $message = "", Throwable $previous = null)/*: never*/
    {
        throw new static($message, E_USER_NOTICE, $previous);
    }

    public static function throwWarning(string $message = "", Throwable $previous = null)/*: never*/
    {
        throw new static($message, E_USER_WARNING, $previous);
    }

    public static function throwError(string $message = "", Throwable $previous = null)/*: never*/
    {
        throw new static($message, E_USER_ERROR, $previous);
    }

    public function trigger(): bool/*false*/
    {
        $debug = defined('STREAM_WRAPPER_DEBUG') && STREAM_WRAPPER_DEBUG;

        $message = $this->getMessage();
        // for debug see tests/bootstrap.php
        if ($debug) {
            $message = preg_replace('@^(#\d+ )(.+?)\((\d+)\)@m', '$1$2:$3', (string) $this);
        }

        if ($this->getCode() === 0) {
            if ($debug && error_reporting() === E_ALL) {
                error_log($message);
            }
        }
        else {
            trigger_error($message, $this->getCode());
        }

        return false;
    }
}
