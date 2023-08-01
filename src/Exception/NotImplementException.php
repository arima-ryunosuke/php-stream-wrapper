<?php
namespace ryunosuke\StreamWrapper\Exception;

use LogicException;

class NotImplementException extends LogicException
{
    public static function throw(string $message = '')/*: never*/
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        throw new static("{$trace['class']}::{$trace['function']} is not implemented" . (strlen($message) ? "($message)" : ""));
    }
}
