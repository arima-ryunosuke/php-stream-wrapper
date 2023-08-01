<?php

namespace ryunosuke\Test\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Stream\SmtpStream;
use ryunosuke\StreamWrapper\Utils\Url;

class SmtpStreamTest extends AbstractStreamTestCase
{
    protected static bool $supportsDirectory = false;
    protected static bool $supportsMetadata  = false;

    public static function setUpBeforeClass(): void
    {
        $STATIC  = static::class;
        $DSN     = static::getConstOrSkip("SMTP_DSN");
        $MAILHOG = static::getConstOrSkip("MAILHOG_URL");

        static::$scheme0   = "smtp";
        static::$scheme1   = "smtp-user";
        static::$namespace = "/user@example.com";

        $url = new Url("dummy://$DSN");
        SmtpStream::register("{$STATIC::$scheme0}://{$url->authority}{$url->querystring}");
        SmtpStream::register("{$STATIC::$scheme1}://{$url->authority}{$STATIC::$namespace}{$url->querystring}");

        file_get_contents("$MAILHOG/api/v1/messages", false, stream_context_create([
            'http' => [
                'method' => 'DELETE',
            ],
        ]));
    }

    function test_resolve()
    {
        $STATIC = static::class;

        $resolved = that(SmtpStream::class)::resolve("{$STATIC::$scheme0}:///");
        $resolved[1]->is('');
        $resolved[2]->is('');

        $resolved = that(SmtpStream::class)::resolve("{$STATIC::$scheme0}://host:1234/hoge@example.jp");
        $resolved[1]->is('hoge@example.jp');
        $resolved[2]->is('');

        $resolved = that(SmtpStream::class)::resolve("{$STATIC::$scheme1}://#extra/subjectX");
        $resolved[1]->is('user@example.com');
        $resolved[2]->is('extra/subjectX');
    }

    function test_stream_not_read()
    {
        if (static::isRoot()) {
            static::markTestSkipped();
        }

        that(@fopen(static::$scheme0 . ":///" . static::$namespace . "#mail", 'r'))->isFalse();
        that(error_get_last()['message'])->contains('failed to open stream', false);

        that(@fopen(static::$scheme0 . ":///" . static::$namespace . "#mail", 'r+'))->isFalse();
        that(error_get_last()['message'])->contains('failed to open stream', false);

        that(@fopen(static::$scheme0 . ":///" . static::$namespace . "#mail", 'w+'))->isFalse();
        that(error_get_last()['message'])->contains('failed to open stream', false);
    }

    function test_send()
    {
        $STATIC  = static::class;
        $MAILHOG = static::getConstOrSkip("MAILHOG_URL");

        file_put_contents("{$STATIC::$scheme0}:///other@example.jp#subject0", 'message0', 0, stream_context_create([
            $STATIC::$scheme0 => [
                'from' => 'local@localhost',
            ],
        ]));
        file_put_contents("{$STATIC::$scheme1}://#subject1", 'message1', FILE_APPEND);

        // https://github.com/mailhog/MailHog/blob/master/docs/APIv1.md
        $messages = json_decode(file_get_contents("$MAILHOG/api/v1/messages"), true);
        that($messages[1]['Content']['Headers']['From'])->is(['local@localhost']);
        that($messages[1]['Content']['Headers']['To'])->is(['other@example.jp']);
        that($messages[1]['Content']['Headers']['Subject'])->is(['subject0']);
        that($messages[1]['Content']['Body'])->is('message0');
        that($messages[0]['Content']['Headers']['From'])->is([sprintf('%s@%s', get_current_user(), gethostname())]);
        that($messages[0]['Content']['Headers']['To'])->is(['user@example.com']);
        that($messages[0]['Content']['Headers']['Subject'])->is(['subject1']);
        that($messages[0]['Content']['Body'])->is('message1');

        that(@file_put_contents("{$STATIC::$scheme0}:///otherXXXXexample.jp#subject0", 'message0'))->is(8);
        that(error_get_last()['message'])->contains('does not comply with addr-spec');
    }
}
