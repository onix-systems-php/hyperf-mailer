<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Contract;

use OnixSystemsPHP\HyperfMailer\Mailable;
use OnixSystemsPHP\HyperfMailer\PendingMail;
use OnixSystemsPHP\HyperfMailer\SentMessage;

interface MailerInterface
{
    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function to(mixed $users): PendingMail;

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function bcc(mixed $users): PendingMail;

    /**
     * Send a new message with only a raw text part.
     */
    public function raw(string $text, mixed $callback): ?SentMessage;

    /**
     * Send a new message using a view.
     */
    public function send(array|Mailable|string $view, array $data = [], \Closure|string $callback = null): ?SentMessage;
}
