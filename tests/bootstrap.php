<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/ryunosuke/phpunit-extension/inc/bootstrap.php';

\ryunosuke\PHPUnit\Actual::generateStub(__DIR__ . '/../src', __DIR__ . '/.stub', 2);

\ryunosuke\PHPUnit\Actual::$functionNamespaces = [];

define('STREAM_WRAPPER_DEBUG', true);
error_reporting(E_ALL);
