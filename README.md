# Hyperf-mailer component

Provides hyperf wrapper for the [symfony/mailer](https://symfony.com/doc/current/mailer.html) package.

You can use features like [High Availability](https://symfony.com/doc/current/mailer.html#high-availability) and [Load Balancing](https://symfony.com/doc/current/mailer.html#load-balancing).

Includes the following classes:

- Event:
    - Action.
- Listener:
    - ActionListener.
- Model:
    - ActionsFilter;
    - Action.
- Repository:
    - ActionRepository.

Install:
```shell script
composer require onix-systems-php/hyperf-mailer
```

Publish config:
```shell script
php bin/hyperf.php vendor:publish onix-systems-php/hyperf-mailer
```

Based on [https://github.com/hyperf-ext/mail](https://github.com/hyperf-ext/mail)
