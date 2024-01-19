<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Mailables;

use Hyperf\Collection\Arr;
use Hyperf\Conditionable\Conditionable;

use function Hyperf\Collection\collect;

class Envelope
{
    use Conditionable;

    /**
     * The address sending the message.
     */
    public null|Address|string $from;

    /**
     * The recipients of the message.
     */
    public array $to;

    /**
     * The recipients receiving a copy of the message.
     */
    public array $cc;

    /**
     * The recipients receiving a blind copy of the message.
     */
    public array $bcc;

    /**
     * The recipients that should be replied to.
     */
    public array $replyTo;

    /**
     * The message's Symfony Message customization callbacks.
     */
    public array|\Closure $using = [];

    /**
     * Create a new message envelope instance.
     */
    public function __construct(
        Address|string $from = null,
        array $to = [],
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        public ?string $subject = null,
        public array $tags = [],
        public array $metadata = [],
        array $using = [],
    ) {
        $this->from = is_string($from) ? new Address($from) : $from;
        $this->to = $this->normalizeAddresses($to);
        $this->cc = $this->normalizeAddresses($cc);
        $this->bcc = $this->normalizeAddresses($bcc);
        $this->replyTo = $this->normalizeAddresses($replyTo);
        $this->using = Arr::wrap($using);
    }

    /**
     * Specify who the message will be "from".
     *
     * @param \OnixSystemsPHP\HyperfMailer\Mailables\Address|string $address
     * @param string|null $name
     * @return $this
     */
    public function from(Address|string $address, string $name = null): Envelope
    {
        $this->from = is_string($address) ? new Address($address, $name) : $address;

        return $this;
    }

    /**
     * Add a "to" recipient to the message envelope.
     *
     * @param \OnixSystemsPHP\HyperfMailer\Mailables\Address|array|string $address
     * @param string|null $name
     * @return $this
     */
    public function to(Address|array|string $address, string $name = null): Envelope
    {
        $this->to = array_merge($this->to, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Add a "cc" recipient to the message envelope.
     *
     * @param \OnixSystemsPHP\HyperfMailer\Mailables\Address|array|string $address
     * @param string|null $name
     * @return $this
     */
    public function cc(Address|array|string $address, string $name = null): static
    {
        $this->cc = array_merge($this->cc, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Add a "bcc" recipient to the message envelope.
     *
     * @param \OnixSystemsPHP\HyperfMailer\Mailables\Address|array|string $address
     * @param string|null $name
     * @return $this
     */
    public function bcc(Address|array|string $address, string $name = null): Envelope
    {
        $this->bcc = array_merge($this->bcc, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Add a "reply to" recipient to the message envelope.
     *
     * @param \OnixSystemsPHP\HyperfMailer\Mailables\Address|array|string $address
     * @param string|null $name
     * @return $this
     */
    public function replyTo(Address|array|string $address, string $name = null): Envelope
    {
        $this->replyTo = array_merge($this->replyTo, $this->normalizeAddresses(
            is_string($name) ? [new Address($address, $name)] : Arr::wrap($address),
        ));

        return $this;
    }

    /**
     * Set the subject of the message.
     *
     * @param string $subject
     * @return $this
     */
    public function subject(string $subject): Envelope
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Add "tags" to the message.
     *
     * @param array $tags
     * @return $this
     */
    public function tags(array $tags): Envelope
    {
        $this->tags = array_merge($this->tags, $tags);

        return $this;
    }

    /**
     * Add a "tag" to the message.
     *
     * @param string $tag
     * @return $this
     */
    public function tag(string $tag): Envelope
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Add metadata to the message.
     *
     * @param string $key
     * @param int|string $value
     * @return $this
     */
    public function metadata(string $key, int|string $value): Envelope
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Add a Symfony Message customization callback to the message.
     *
     * @param \Closure $callback
     * @return $this
     */
    public function using(\Closure $callback): Envelope
    {
        $this->using[] = $callback;

        return $this;
    }

    /**
     * Determine if the message is from the given address.
     *
     * @param string $address
     * @param string|null $name
     * @return bool
     */
    public function isFrom(string $address, string $name = null): bool
    {
        if (is_null($name)) {
            return $this->from->address === $address;
        }

        return $this->from->address === $address
            && $this->from->name === $name;
    }

    /**
     * Determine if the message has the given address as a recipient.
     *
     * @param string $address
     * @param string|null $name
     * @return bool
     */
    public function hasTo(string $address, string $name = null): bool
    {
        return $this->hasRecipient($this->to, $address, $name);
    }

    /**
     * Determine if the message has the given address as a "cc" recipient.
     *
     * @param string $address
     * @param string|null $name
     * @return bool
     */
    public function hasCc(string $address, string $name = null): bool
    {
        return $this->hasRecipient($this->cc, $address, $name);
    }

    /**
     * Determine if the message has the given address as a "bcc" recipient.
     *
     * @param string $address
     * @param string|null $name
     * @return bool
     */
    public function hasBcc(string $address, string $name = null): bool
    {
        return $this->hasRecipient($this->bcc, $address, $name);
    }

    /**
     * Determine if the message has the given address as a "reply to" recipient.
     *
     * @param string $address
     * @param string|null $name
     * @return bool
     */
    public function hasReplyTo(string $address, string $name = null): bool
    {
        return $this->hasRecipient($this->replyTo, $address, $name);
    }

    /**
     * Determine if the message has the given subject.
     *
     * @param string $subject
     * @return bool
     */
    public function hasSubject(string $subject): bool
    {
        return $this->subject === $subject;
    }

    /**
     * Determine if the message has the given metadata.
     *
     * @param  string  $key
     * @param  string  $value
     * @return bool
     */
    public function hasMetadata(string $key, string $value): bool
    {
        return isset($this->metadata[$key]) && (string) $this->metadata[$key] === $value;
    }

    /**
     * Determine if the message has the given recipient.
     *
     * @param array $recipients
     * @param string $address
     * @param string|null $name
     * @return bool
     */
    protected function hasRecipient(array $recipients, string $address, ?string $name = null): bool
    {
        return collect($recipients)->contains(function ($recipient) use ($address, $name) {
            if (is_null($name)) {
                return $recipient->address === $address;
            }

            return $recipient->address === $address
                && $recipient->name === $name;
        });
    }

    /**
     * Normalize the given array of addresses.
     *
     * @param array $addresses
     * @return array
     */
    protected function normalizeAddresses(array $addresses): array
    {
        return collect($addresses)->map(function ($address) {
            return is_string($address) ? new Address($address) : $address;
        })->all();
    }
}
