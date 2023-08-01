<?php

namespace ryunosuke\Test\StreamWrapper\Utils;

use ryunosuke\StreamWrapper\Utils\Stat;
use ryunosuke\Test\StreamWrapper\Stream\AbstractStreamTestCase;

class StatTest extends AbstractStreamTestCase
{
    function test_filetype()
    {
        $stat = new Stat([]);

        $stat->mode = 004_0777;
        that($stat)->filetype()->is('dir');

        $stat->mode = 010_0777;
        that($stat)->filetype()->is('file');

        $stat->mode = 070_0777;
        that($stat)->filetype()->is('unknown');
    }

    function test_touch()
    {
        $stat = new Stat([]);

        that($stat)->touch(null, null)->is([]);

        that($stat)->touch(123, null)->is([
            "mtime" => 123,
            "ctime" => time(),
        ]);

        that($stat)->touch(null, 123)->is([
            "atime" => 123,
            "ctime" => time(),
        ]);

        that($stat)->touch(123, 456)->is([
            "mtime" => 123,
            "atime" => 456,
            "ctime" => time(),
        ]);
    }

    function test_ch()
    {
        $stat = new Stat([
            'size'  => 12345,
            'mtime' => 1234567890,
        ]);

        that($stat->array())->is([
            "dev"     => 0,
            "ino"     => 0,
            "mode"    => 010_0777,
            "nlink"   => 1,
            "uid"     => 0,
            "gid"     => 0,
            "rdev"    => -1,
            "size"    => 12345,
            "atime"   => 0,
            "mtime"   => 1234567890,
            "ctime"   => 0,
            "blksize" => -1,
            "blocks"  => -1,
        ]);

        that($stat)->chmod(0700)->is([
            "mode"  => 010_0700,
            "ctime" => time(),
        ]);
        that($stat)->chown(48)->is([
            "uid"   => 48,
            "ctime" => time(),
        ]);
        that($stat)->chgrp(27)->is([
            "gid"   => 27,
            "ctime" => time(),
        ]);
        that($stat)->mode->is(0100700);
        that($stat)->uid->is(48);
        that($stat)->gid->is(27);
    }

    public static function providePermission()
    {
        return [
            [
                '0700',
                [
                    'ownerR' => true,
                    'ownerW' => true,
                    'ownerX' => true,
                    'groupR' => false,
                    'groupW' => false,
                    'groupX' => false,
                    'otherR' => false,
                    'otherW' => false,
                    'otherX' => false,
                ],
            ],
            [
                '0070',
                [
                    'ownerR' => false,
                    'ownerW' => false,
                    'ownerX' => false,
                    'groupR' => true,
                    'groupW' => true,
                    'groupX' => true,
                    'otherR' => false,
                    'otherW' => false,
                    'otherX' => false,
                ],
            ],
            [
                '0007',
                [
                    'ownerR' => false,
                    'ownerW' => false,
                    'ownerX' => false,
                    'groupR' => false,
                    'groupW' => false,
                    'groupX' => false,
                    'otherR' => true,
                    'otherW' => true,
                    'otherX' => true,
                ],
            ],
            [
                '0444',
                [
                    'ownerR' => true,
                    'ownerW' => false,
                    'ownerX' => false,
                    'groupR' => true,
                    'groupW' => false,
                    'groupX' => false,
                    'otherR' => true,
                    'otherW' => false,
                    'otherX' => false,
                ],
            ],
            [
                '0222',
                [
                    'ownerR' => false,
                    'ownerW' => true,
                    'ownerX' => false,
                    'groupR' => false,
                    'groupW' => true,
                    'groupX' => false,
                    'otherR' => false,
                    'otherW' => true,
                    'otherX' => false,
                ],
            ],
            [
                '0111',
                [
                    'ownerR' => false,
                    'ownerW' => false,
                    'ownerX' => true,
                    'groupR' => false,
                    'groupW' => false,
                    'groupX' => true,
                    'otherR' => false,
                    'otherW' => false,
                    'otherX' => true,
                ],
            ],
            [
                '0000',
                [
                    'ownerR' => false,
                    'ownerW' => false,
                    'ownerX' => false,
                    'groupR' => false,
                    'groupW' => false,
                    'groupX' => false,
                    'otherR' => false,
                    'otherW' => false,
                    'otherX' => false,
                ],
            ],
        ];
    }

    /**
     * @dataProvider providePermission
     */
    function test_perms($perm, $rwx)
    {
        $stat = new Stat([
            'uid' => 48,
            'gid' => 27,
        ]);

        $stat->chmod(intval($perm, 8));
        that($stat)->mode->is(0100000 + intval($perm, 8));

        // both
        that($stat)->isReadable(48, 27)->isAny([$rwx['ownerR'], $rwx['groupR']]);
        that($stat)->isWritable(48, 27)->isAny([$rwx['ownerW'], $rwx['groupW']]);
        that($stat)->isExecutable(48, 27)->isAny([$rwx['ownerX'], $rwx['groupX']]);
        // owner
        that($stat)->isReadable(48, 99)->is($rwx['ownerR']);
        that($stat)->isWritable(48, 99)->is($rwx['ownerW']);
        that($stat)->isExecutable(48, 99)->is($rwx['ownerX']);
        // group
        that($stat)->isReadable(99, 27)->is($rwx['groupR']);
        that($stat)->isWritable(99, 27)->is($rwx['groupW']);
        that($stat)->isExecutable(99, 27)->is($rwx['groupX']);
        // other
        that($stat)->isReadable(99, 99)->is($rwx['otherR']);
        that($stat)->isWritable(99, 99)->is($rwx['otherW']);
        that($stat)->isExecutable(99, 99)->is($rwx['otherX']);
    }
}
