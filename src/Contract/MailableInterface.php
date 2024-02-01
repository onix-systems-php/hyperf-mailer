<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Contract;

use OnixSystemsPHP\HyperfMailer\Mailer;
use OnixSystemsPHP\HyperfMailer\SentMessage;

interface MailableInterface
{
    /**
     * Send the message using the given mailer.
     */
    public function send(Mailer $mailer): ?SentMessage;

    /**
     * Queue the given message.
     */
    public function queue(string $queue): mixed;

    /**
     * Deliver the queued message after (n) seconds.
     */
    public function later(\DateInterval|\DateTimeInterface|int $delay, string $queue): mixed;

    /**
     * Set the recipients of the message.
     */
    public function cc(array|object|string $address, string $name = null): self;

    /**
     * Set the recipients of the message.
     */
    public function bcc(array|object|string $address, string $name = null): self;

    /**
     * Set the recipients of the message.
     */
    public function to(array|object|string $address, string $name = null): self;

    /**
     * Set the locale of the message.
     */
    public function locale(string $locale): self;

    /**
     * Set the name of the mailer that should be used to send the message.
     */
    public function mailer(string $mailer): self;
}
