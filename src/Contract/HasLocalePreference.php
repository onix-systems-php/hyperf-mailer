<?php

declare(strict_types=1);
namespace OnixSystemsPHP\HyperfMailer\Contract;

interface HasLocalePreference
{
    /**
     * Get the preferred locale of the entity.
     */
    public function getPreferredLocale(): ?string;
}
