<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Support\Traits\ForwardsCalls;
use OnixSystemsPHP\HyperfMailer\Contract\Attachable;

/**
 * @mixin Message
 */
class TextMessage
{
    use ForwardsCalls;

    /**
     * Create a new text message instance.
     */
    public function __construct(protected Message $message) {}

    /**
     * Dynamically pass missing methods to the underlying message instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->message, $method, $parameters);
    }

    /**
     * Embed a file in the message and get the CID.
     */
    public function embed(Attachable|Attachment|string $file): string
    {
        return '';
    }

    public function embedData(string $data, string $name, ?string $contentType = null): string
    {
        return '';
    }
}
