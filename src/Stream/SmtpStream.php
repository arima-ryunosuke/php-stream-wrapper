<?php
namespace ryunosuke\StreamWrapper\Stream;

use ryunosuke\StreamWrapper\Exception\ErrorException;
use ryunosuke\StreamWrapper\Mixin\StreamTrait;
use ryunosuke\StreamWrapper\Utils\Stat;
use ryunosuke\StreamWrapper\Utils\Url;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

class SmtpStream extends AbstractStream
{
    use StreamTrait;

    protected static array $drivers = [];

    /** @return array{SmtpDriver, string, string} */
    public static function resolve(string $url): array
    {
        $local   = new Url($url);
        $default = static::$default[$local->scheme];
        $merged  = $local->merge($default);

        $driver = static::$drivers[$merged->dsn] ??= (function () use ($merged) {
            return new SmtpDriver($merged->host, $merged->port, $merged->user, $merged->pass);
        })();
        return [$driver, trim($merged->path ?? '', '/'), $merged->fragment];
    }

    protected function getMetadata(string $url): ?array
    {
        // write only always
        return [
            'mode' => Stat::FILE | 0222,
        ];
    }

    protected function selectFile(string $url, ?array &$metadata, array $contextOptions): string
    {
        // never not found(write only)
        ErrorException::throwWarning("$url: is not found");
    }

    protected function createFile(string $url, string $contents, array $metadata, array $contextOptions): void
    {
        [$driver, $recipient, $subject] = static::resolve($url);

        $driver->execute($contextOptions['from'] ?? sprintf('%s@%s', get_current_user(), gethostname()), $recipient, $subject, $contents);
    }

    protected function appendFile(string $url, string $contents, array $contextOptions): void
    {
        [$driver, $recipient, $subject] = static::resolve($url);

        $driver->execute($contextOptions['from'] ?? sprintf('%s@%s', get_current_user(), gethostname()), $recipient, $subject, $contents);
    }
}

class SmtpDriver
{
    private Mailer $mailer;

    public function __construct(string $host, int $port, ?string $user, ?string $pass)
    {
        $transport = new EsmtpTransport($host, $port);
        if ($user !== null) {
            $transport->setUsername($user);
        }
        if ($pass !== null) {
            $transport->setPassword($pass);
        }
        $this->mailer = new Mailer($transport);
    }

    public function execute(string $from, string $recipient, string $subject, string $body)
    {
        if (strlen($body)) {
            try {
                $this->mailer->send((new Email())->from($from)->to($recipient)->subject($subject)->text($body));
            }
            catch (TransportException|RfcComplianceException $e) {
                ErrorException::throwWarning($e->getMessage(), $e);
            }
        }
    }
}
