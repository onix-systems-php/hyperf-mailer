<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Support\Traits\ForwardsCalls;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use function Hyperf\Collection\collect;

class SentMessage
{
    use ForwardsCalls;

    /**
     * Create a new SentMessage instance.
     *
     * @param \Symfony\Component\Mailer\SentMessage $sentMessage
     */
    public function __construct(protected SymfonySentMessage $sentMessage)
    {
    }

    /**
     * Dynamically pass missing methods to the Symfony instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->forwardCallTo($this->sentMessage, $method, $parameters);
    }

    /**
     * Get the serializable representation of the object.
     *
     * @return array
     */
    public function __serialize()
    {
        $hasAttachments = collect($this->sentMessage->getOriginalMessage()->getAttachments())->isNotEmpty();

        return [
            'hasAttachments' => $hasAttachments,
            'sentMessage' => $hasAttachments ? base64_encode(serialize($this->sentMessage)) : $this->sentMessage,
        ];
    }

    /**
     * Marshal the object from its serialized data.
     *
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data)
    {
        $hasAttachments = ($data['hasAttachments'] ?? false) === true;

        $this->sentMessage = $hasAttachments ? unserialize(base64_decode($data['sentMessage'])) : $data['sentMessage'];
    }

    /**
     * Get the underlying Symfony Email instance.
     *
     * @return SymfonySentMessage
     */
    public function getSymfonySentMessage(): SymfonySentMessage
    {
        return $this->sentMessage;
    }
}
