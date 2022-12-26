<?php

declare(strict_types=1);
namespace OnixSystemsPHP\HyperfMailer;

use OnixSystemsPHP\HyperfMailer\Command\GenMailCommand;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                MailManagerInterface::class => MailManager::class,
            ],
            'commands' => [
                GenMailCommand::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'listeners' => [
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for onix-systems-php/hyperf-mailer.',
                    'source' => __DIR__ . '/../publish/mail.php',
                    'destination' => BASE_PATH . '/config/autoload/mail.php',
                ],
                [
                    'id' => 'queue_config',
                    'description' => 'The async queue config for onix-systems-php/hyperf-mailer.',
                    'source' => __DIR__ . '/../publish/async_queue.php',
                    'destination' => BASE_PATH . '/config/autoload/async_queue.php',
                ],
            ],
        ];
    }
}
