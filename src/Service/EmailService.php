<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfMailer\Contract\HasMailAddress;
use OnixSystemsPHP\HyperfMailer\Mail;
use OnixSystemsPHP\HyperfMailer\Mailable;

#[Service]
class EmailService
{
    public function run(array|HasMailAddress|string $to, Mailable $mail): void
    {
        Mail::to($to)->queue($mail, 'emails');
    }
}
