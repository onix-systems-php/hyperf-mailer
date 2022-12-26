<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfMailer\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfMailer\Contract\HasMailAddress;
use OnixSystemsPHP\HyperfMailer\Mail;
use OnixSystemsPHP\HyperfMailer\Mailable;

#[Service]
class EmailService
{
    public function run(HasMailAddress $to, Mailable $mail): void
    {
        Mail::to($to)->queue($mail, 'emails');
    }
}
