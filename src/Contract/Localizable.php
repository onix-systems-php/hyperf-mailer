<?php

namespace OnixSystemsPHP\HyperfMailer\Contract;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\TranslatorInterface;

trait Localizable
{
    public function withLocale(string $locale = null, \Closure $callback)
    {
        if (is_null($locale)) {
            return $callback;
        }

        $translator = ApplicationContext::getContainer()->get(TranslatorInterface::class);

        $original = $translator->getLocale();

        try {
            $translator->setLocale($locale);
            return $callback();
        } finally {
            $translator->setLocale($original);
        }
    }
}
