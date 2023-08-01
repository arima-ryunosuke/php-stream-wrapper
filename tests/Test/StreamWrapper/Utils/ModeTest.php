<?php

namespace ryunosuke\Test\StreamWrapper\Utils;

use ryunosuke\StreamWrapper\Utils\Mode;
use ryunosuke\Test\StreamWrapper\Stream\AbstractStreamTestCase;

class ModeTest extends AbstractStreamTestCase
{
    public static function provideModes()
    {
        return [
            [
                'r',
                [
                    'isReadMode'    => true,
                    'isWriteMode'   => false,
                    'isExcludeMode' => false,
                    'isCreateMode'  => false,
                    'isAppendMode'  => false,
                    'isReadable'    => true,
                    'isWritable'    => false,
                    'isAppendable'  => false,
                ],
            ],
            [
                'r+',
                [
                    'isReadMode'    => true,
                    'isWriteMode'   => false,
                    'isExcludeMode' => false,
                    'isCreateMode'  => false,
                    'isAppendMode'  => false,
                    'isReadable'    => true,
                    'isWritable'    => true,
                    'isAppendable'  => false,
                ],
            ],
            [
                'w',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => true,
                    'isExcludeMode' => false,
                    'isCreateMode'  => false,
                    'isAppendMode'  => false,
                    'isReadable'    => false,
                    'isWritable'    => true,
                    'isAppendable'  => false,
                ],
            ],
            [
                'w+',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => true,
                    'isExcludeMode' => false,
                    'isCreateMode'  => false,
                    'isAppendMode'  => false,
                    'isReadable'    => true,
                    'isWritable'    => true,
                    'isAppendable'  => false,
                ],
            ],
            [
                'x',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => false,
                    'isExcludeMode' => true,
                    'isCreateMode'  => false,
                    'isAppendMode'  => false,
                    'isReadable'    => false,
                    'isWritable'    => true,
                    'isAppendable'  => false,
                ],
            ],
            [
                'x+',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => false,
                    'isExcludeMode' => true,
                    'isCreateMode'  => false,
                    'isAppendMode'  => false,
                    'isReadable'    => true,
                    'isWritable'    => true,
                    'isAppendable'  => false,
                ],
            ],
            [
                'c',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => false,
                    'isExcludeMode' => false,
                    'isCreateMode'  => true,
                    'isAppendMode'  => false,
                    'isReadable'    => false,
                    'isWritable'    => true,
                    'isAppendable'  => false,
                ],
            ],
            [
                'c+',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => false,
                    'isExcludeMode' => false,
                    'isCreateMode'  => true,
                    'isAppendMode'  => false,
                    'isReadable'    => true,
                    'isWritable'    => true,
                    'isAppendable'  => false,
                ],
            ],
            [
                'a',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => false,
                    'isExcludeMode' => false,
                    'isCreateMode'  => false,
                    'isAppendMode'  => true,
                    'isReadable'    => false,
                    'isWritable'    => true,
                    'isAppendable'  => true,
                ],
            ],
            [
                'a+',
                [
                    'isReadMode'    => false,
                    'isWriteMode'   => false,
                    'isExcludeMode' => false,
                    'isCreateMode'  => false,
                    'isAppendMode'  => true,
                    'isReadable'    => true,
                    'isWritable'    => true,
                    'isAppendable'  => true,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideModes
     */
    function test_all($mode, $flags)
    {
        $mode = new Mode($mode);
        that($mode)->isReadMode()->is($flags['isReadMode']);
        that($mode)->isWriteMode()->is($flags['isWriteMode']);
        that($mode)->isExcludeMode()->is($flags['isExcludeMode']);
        that($mode)->isCreateMode()->is($flags['isCreateMode']);
        that($mode)->isAppendMode()->is($flags['isAppendMode']);
        that($mode)->isReadable()->is($flags['isReadable']);
        that($mode)->isWritable()->is($flags['isWritable']);
        that($mode)->isAppendable()->is($flags['isAppendable']);
    }

    function test_misc()
    {
        that((string) new Mode('r+bt'))->is('r+');
        that(Mode::class)->new('d+')->wasThrown('is invalid');
    }
}
