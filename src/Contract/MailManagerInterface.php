<?php

declare(strict_types=1);
namespace OnixSystemsPHP\HyperfMailer\Contract;

interface MailManagerInterface
{
    /**
     * Get a mailer instance by name.
     */
    public function get(string $name): MailerInterface;
}
