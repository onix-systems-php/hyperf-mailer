<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Collection\Collection;
use Hyperf\Context\ApplicationContext;
use OnixSystemsPHP\HyperfMailer\Contract\HasMailAddress;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;

/**
 * @method static PendingMail to(array|Collection|HasMailAddress|string $users)
 * @method static PendingMail cc(array|Collection|HasMailAddress|string $users)
 * @method static PendingMail bcc(array|Collection|HasMailAddress|string $users)
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
