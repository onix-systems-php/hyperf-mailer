<?php

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Support\Traits\ForwardsCalls;

/**
 * @mixin \OnixSystemsPHP\HyperfMailer\Message
 */
class TextMessage
{
    use ForwardsCalls;

    protected $message;

    public function __construct()
    {
    }

    /**
     * @param Attac
     * @return string
     */
    public function embed($file)
    {
        return '';
    }

    /**
     * @param $data
     * @param $name
     * @param $contentType
     * @return string
     */
    public function embedData($data, $name, $contentType = null): string
    {
        return '';
    }

    /**
     * Dynamically pass missing methods to the underlying message instance.
     * @param $method
     * @param $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->forwardCallTo($this->message, $method, $parameters);
    }
}
