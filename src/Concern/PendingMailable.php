<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Concern;

use Hyperf\Collection\Collection;
use OnixSystemsPHP\HyperfMailer\Contract\HasMailAddress;
use OnixSystemsPHP\HyperfMailer\PendingMail;

trait PendingMailable
{
    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function to(mixed $users): PendingMail
    {
        return (new PendingMail($this))->to($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function cc(array|Collection|HasMailAddress $users): PendingMail
    {
        return (new PendingMail($this))->cc($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function bcc(mixed $users): PendingMail
    {
        return (new PendingMail($this))->bcc($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function locale(string $locale): PendingMail
    {
        return (new PendingMail($this))->locale($locale);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function mailer(string $name): PendingMail
    {
        return (new PendingMail($this))->mailer($name);
    }
}
