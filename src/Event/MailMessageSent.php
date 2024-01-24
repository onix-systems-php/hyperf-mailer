<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Event;

use OnixSystemsPHP\HyperfMailer\SentMessage;
use Symfony\Component\Mime\RawMessage;
use function Hyperf\Collection\collect;

class MailMessageSent
{
    /**
     * The message that was sent.
     */
    public SentMessage $sent;

    /**
     * The message data.
     */
    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(SentMessage $message, array $data = [])
    {
        $this->data = $data;
        $this->sent = $message;
    }

    /**
     * Get the serializable representation of the object.
     */
    public function __serialize(): array
    {
        $hasAttachments = collect($this->message->getAttachments())->isNotEmpty();

        return [
            'sent' => $this->sent,
            'data' => $hasAttachments ? base64_encode(serialize($this->data)) : $this->data,
            'hasAttachments' => $hasAttachments,
        ];
    }

    /**
     * Marshal the object from its serialized data.
     */
    public function __unserialize(array $data): void
    {
        $this->sent = $data['sent'];

        $this->data = (($data['hasAttachments'] ?? false) === true)
            ? unserialize(base64_decode($data['data']))
            : $data['data'];
    }

    /**
     * Dynamically get the original message.
     *
     * @param string $key
     * @return RawMessage
     *
     * @throws \Exception
     */
    public function __get(string $key): RawMessage
    {
        if ($key === 'message') {
            return $this->sent->getOriginalMessage();
        }

        throw new \Exception('Unable to access undefined property on ' . __CLASS__ . ': ' . $key);
    }
}
