<?php

declare(strict_types=1);
namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Utils\ApplicationContext;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;

/**
 * @method static PendingMail to(mixed $users)
 * @method static PendingMail cc(mixed $users)
 * @method static PendingMail bcc(mixed $users)
 * @method static bool later(Contract\MailableInterface $mailable, int $delay, ?string $queue = null)
 * @method static bool queue(Contract\MailableInterface $mailable, ?string $queue = null)
 * @method static null|int send(Contract\MailableInterface $mailable)
 *
 * @see MailManager
 */
abstract class Mail
{
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::getManager();

        return $instance->{$method}(...$args);
    }

    public static function mailer(string $name): PendingMail
    {
        return new PendingMail(static::getManager()->get($name));
    }

    protected static function getManager(): MailManagerInterface
    {
        return ApplicationContext::getContainer()->get(MailManagerInterface::class);
    }
}
