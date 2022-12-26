<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\AsyncQueue\Job;
use Hyperf\Utils\ApplicationContext;
use OnixSystemsPHP\HyperfMailer\Contract\MailableInterface;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;

class QueuedMailableJob extends Job
{
    public function __construct(public MailableInterface $mailable)
    {
    }

    public function handle()
    {
        $this->mailable->send(ApplicationContext::getContainer()->get(MailManagerInterface::class));
    }
}
