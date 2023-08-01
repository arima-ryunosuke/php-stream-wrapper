<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\Test\AbstractTestCase;

abstract class AbstractStreamTestCase extends AbstractTestCase
{
    protected static string $scheme0; // standard
    protected static string $scheme1; // specified-namespace
    protected static string $namespace;

    protected static bool $supportsDirectory;
    protected static bool $supportsMetadata;
}
