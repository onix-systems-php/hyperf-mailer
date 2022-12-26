<?php

declare(strict_types=1);
namespace OnixSystemsPHP\HyperfMailer\Event;

use Symfony\Component\Mime\Email;

class MailMessageSending
{
    /**
     * The Symfony message instance.
     */
    public Email $message;

    /**
     * The message data.
     */
    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(Email $message, array $data = [])
    {
        $this->data = $data;
        $this->message = $message;
    }
}
