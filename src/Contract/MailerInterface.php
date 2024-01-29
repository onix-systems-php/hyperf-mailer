<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Contract;

interface MailerInterface
{
    /**
     * Render the given message as a view.
     */
    public function render(MailableInterface $mailable): string;


    /**
     * Send a new message using a mailable instance.
     */
    public function send(MailableInterface $mailable): void;

    /**
     * Queue a new e-mail message for sending.
     */
    public function queue(MailableInterface $mailable, ?string $queue = null): bool;

    /**
     * Queue a new e-mail message for sending.
     */
    public function later(MailableInterface $mailable, int $delay, ?string $queue = null): bool;
}
