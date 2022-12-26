# Hyperf-mailer component

Provides hyperf wrapper for the [symfony/mailer](https://symfony.com/doc/current/mailer.html) package.

You can use features like [High Availability](https://symfony.com/doc/current/mailer.html#high-availability) and [Load Balancing](https://symfony.com/doc/current/mailer.html#load-balancing).

Includes the following classes:

- Command:
    - GenMailCommand;
- Contract:
    - HasLocalePreference;
    - HasMailAddress;
    - ShouldQueue;
- Event:
    - MailMessageSending;
    - MailMessageSent.
- Service:
    - EmailService.
- Mail;
- Mailable.

Install:
```shell script
composer require onix-systems-php/hyperf-mailer
```

Publish config:
```shell script
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-mailer
```

Code Example:
```php 
<?php
declare(strict_types=1);

namespace App\Mail\Users;

use OnixSystemsPHP\HyperfMailer\Contract\ShouldQueue;
use OnixSystemsPHP\HyperfMailer\Mailable;

class TestEmail extends Mailable implements ShouldQueue
{
    public function __construct(private string $name)
    {
    }

    public function build(): void
    {
        $this
            ->subject('PHP Department welcome')
            ->textBody(sprintf('Hello, %s!', $this->name));
    }
}

...

$this->emailService->run(
    new User([
        'email' => $recipient[0],
        'first_name' => $recipient[1],
    ]),
    new TestEmail($recipient[1])
);
```

Based on [https://github.com/hyperf-ext/mail](https://github.com/hyperf-ext/mail)
