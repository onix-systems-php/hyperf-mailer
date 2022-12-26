<?php

declare(strict_types=1);
namespace OnixSystemsPHP\HyperfMailer\Contract;

interface HasMailAddress
{
    /**
     * Get the mail address of the entity.
     */
    public function getMailAddress(): ?string;

    /**
     * Get the mail address display name of the entity.
     */
    public function getMailAddressDisplayName(): ?string;
}
