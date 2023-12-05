<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Contract;

interface MailManagerInterface
{
    /**
     * Get a mailer instance by name.
     */
    public function get(string $name): MailerInterface;
}
