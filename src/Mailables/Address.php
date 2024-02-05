<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Mailables;

class Address
{
    /**
     * Create a new address instance.
     */
    public function __construct(public string $address, public ?string $name = null) {}
}
