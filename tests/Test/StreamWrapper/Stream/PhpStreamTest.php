<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Stream\PhpStream;

class PhpStreamTest extends AbstractStreamTestCase
{
    function test_all()
    {
        PhpStream::override();

        $fp = fopen('php://stdin.json?a=A#fragment', 'r');
        that(stream_get_meta_data($fp)['uri'])->is('php://stdin.json?a=A#fragment');
        fclose($fp);

        $fp = fopen('php://output.json', 'w');
        ob_start();
        fwrite($fp, 'test');
        that(ob_get_clean())->is('test');
        fclose($fp);

        $fp = fopen('php://temp/maxmemory:1024.json', 'w');
        fwrite($fp, 'test');
        rewind($fp);
        that(stream_get_contents($fp))->is('test');
        fclose($fp);

        $file = sys_get_temp_dir() . '/example.txt';
        @unlink($file);
        $fp = fopen('php://filter/write=string.rot13/resource=' . $file, 'w+');
        fwrite($fp, 'test');
        rewind($fp);
        that(stream_get_contents($fp))->is('grfg');
        fclose($fp);
        that(file_get_contents($file))->is('grfg');

        stream_wrapper_restore('php');
    }
}
