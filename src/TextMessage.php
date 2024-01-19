<?php

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Support\Traits\ForwardsCalls;
use OnixSystemsPHP\HyperfMailer\Contract\Attachable;

/**
 * @mixin \OnixSystemsPHP\HyperfMailer\Message
 */
class TextMessage
{
    use ForwardsCalls;

    /**
     * Create a new text message instance.
     *
     * @param \OnixSystemsPHP\HyperfMailer\Message $message
     * @return void
     */
    public function __construct(protected Message $message)
    {
    }

    /**
     * Embed a file in the message and get the CID
     *
     * @param string|\OnixSystemsPHP\HyperfMailer\Attachment|\OnixSystemsPHP\HyperfMailer\Contract\Attachable $file
     * @return string
     */
    public function embed(string|Attachment|Attachable $file): string
    {
        return '';
    }

    /**
     * @param string $data
     * @param string $name
     * @param string|null $contentType
     * @return string
     */
    public function embedData(string $data, string $name, ?string $contentType = null): string
    {
        return '';
    }

    /**
     * Dynamically pass missing methods to the underlying message instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->message, $method, $parameters);
    }
}
