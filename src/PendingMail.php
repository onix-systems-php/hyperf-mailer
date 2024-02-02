<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Conditionable\Conditionable;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use OnixSystemsPHP\HyperfMailer\Contract\HasLocalePreference;
use OnixSystemsPHP\HyperfMailer\Contract\HasMailAddress;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;

use function Hyperf\Tappable\tap;

class PendingMail
{
    use Conditionable;

    /**
     * The mailer instance.
     */
    protected Contract\MailerInterface|MailManagerInterface $mailer;

    /**
     * The locale of the message.
     */
    protected string $locale;

    /**
     * The "to" recipients of the message.
     */
    protected array $to = [];

    /**
     * The "cc" recipients of the message.
     */
    protected array $cc = [];

    /**
     * The "bcc" recipients of the message.
     */
    protected array $bcc = [];

    /**
     * Create a new mailable mailer instance.
     */
    public function __construct(Contract\MailerInterface|MailManagerInterface $mailer)
    {
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $this->locale = $config->get('translation.fallback_locale', 'en-US');
        $this->mailer = $mailer;
    }

    /**
     * Set the locale of the message.
     */
    public function locale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set the recipients of the message.
     */
    public function to(mixed $users): static
    {
        if ($users instanceof HasMailAddress) {
            $this->to = [['address' => $users->getMailAddress(), 'name' => $users->getMailAddressDisplayName()]];
        } else {
            $this->to = is_array($users) ? $users : (array) $users;
        }

        if (empty($this->locale)
            && $users instanceof HasLocalePreference
            && is_string($locale = $users->preferredLocale())
        ) {
            $this->locale($locale);
        }

        return $this;
    }

    /**
     * Set the recipients of the message.
     */
    public function cc(mixed $users): static
    {
        $this->cc = $users;

        return $this;
    }

    /**
     * Set the recipients of the message.
     */
    public function bcc(mixed $users): static
    {
        $this->bcc = $users;

        return $this;
    }

    /**
     * Send a new mailable message instance.
     */
    public function send(Mailable $mailable): null|SentMessage
    {
        return $this->mailer->send($this->fill($mailable));
    }

    /**
     * Push the given mailable onto the queue.
     */
    public function queue(Mailable $mailable, ?string $queue = null): mixed
    {
        return $this->mailer->queue($this->fill($mailable), $queue);
    }

    /**
     * Deliver the queued message after (n) seconds.
     * @param mixed $delay
     */
    public function later($delay, Mailable $mailable): bool
    {
        return $this->mailer->later($delay, $this->fill($mailable));
    }

    /**
     * Populate the mailable with the addresses.
     */
    protected function fill(Mailable $mailable): Mailable
    {
        return tap($mailable->to($this->to)
            ->cc($this->cc)
            ->bcc($this->bcc), function (Mailable $mailable) {
                if ($this->locale) {
                    $mailable->locale($this->locale);
                }
            });
    }
}
