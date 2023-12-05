<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Contract;

interface HasLocalePreference
{
    /**
     * Get the preferred locale of the entity.
     */
    public function getPreferredLocale(): ?string;
}
