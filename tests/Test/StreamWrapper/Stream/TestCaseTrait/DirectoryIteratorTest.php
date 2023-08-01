<?php

namespace ryunosuke\Test\StreamWrapper\Stream\TestCaseTrait;

trait DirectoryIteratorTest
{
    function test_scandir()
    {
        $root = static::$scheme0 . "://" . static::$namespace;

        touch("$root/sub1like.txt");
        touch("$root/sub1/file1.txt");
        touch("$root/sub1/file2.txt");
        touch("$root/sub1/sub2like.txt");
        touch("$root/sub1/sub2/file3.txt");
        touch("$root/sub1/sub2/file4.txt");
        touch("$root/sub1/sub2/sub3like.txt");
        touch("$root/sub1/sub2/sub3/file5.txt");
        touch("$root/sub1/sub2/sub3/file6.txt");

        $scandir = function ($dir) {
            $scandir = @scandir($dir);
            if ($scandir === false) {
                return false;
            }
            $files = array_values(array_filter($scandir, fn($v) => !in_array($v, ['.', '..'], true)));
            sort($files);
            return $files;
        };
        that($scandir("$root/notfounddir"))->is(false);
        that($scandir("$root/"))->contains("sub1");
        that($scandir("$root/sub1"))->is(["file1.txt", "file2.txt", "sub2", "sub2like.txt"]);
        that($scandir("$root/sub1/sub2"))->is(["file3.txt", "file4.txt", "sub3", "sub3like.txt"]);
        that($scandir("$root/sub1/sub2/sub3"))->is(["file5.txt", "file6.txt"]);

        if (is_dir("$root/sub1")) {
            that(@scandir("$root/sub1/file1.txt"))->isFalse();
        }
    }

    function test_opendir()
    {
        $root = static::$scheme0 . "://" . static::$namespace;

        touch("$root/sub2/file1.txt");
        touch("$root/sub2/file2.txt");

        $dir = opendir("$root/sub2");
        that(rewinddir($dir))->is(null);
        that(readdir($dir))->isAny(['file1.txt', '.']);
        that(closedir($dir))->is(null);
    }
}
