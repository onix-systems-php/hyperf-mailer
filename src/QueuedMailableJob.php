<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use OnixSystemsPHP\HyperfMailer\Contract\MailableInterface;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;

class QueuedMailableJob extends Job
{
    public function __construct(public MailableInterface $mailable) {}

    public function handle(): void
    {
        $this->mailable->send(ApplicationContext::getContainer()->get(MailManagerInterface::class));
    }
}
