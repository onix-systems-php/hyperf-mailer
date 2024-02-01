<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Context\ApplicationContext;
use Hyperf\Macroable\Macroable;
use Hyperf\Stringable\Str;
use Hyperf\View\RenderInterface;
use OnixSystemsPHP\HyperfMailer\Concern\PendingMailable;
use OnixSystemsPHP\HyperfMailer\Contract\MailableInterface;
use OnixSystemsPHP\HyperfMailer\Contract\MailerInterface;
use OnixSystemsPHP\HyperfMailer\Contract\ShouldQueue;
use OnixSystemsPHP\HyperfMailer\Event\MailMessageSending;
use OnixSystemsPHP\HyperfMailer\Event\MailMessageSent;
use OnixSystemsPHP\HyperfMailer\Mailable as MailableContract;
use OnixSystemsPHP\HyperfMailer\Mailables\Address;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerInterface;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class Mailer implements MailerInterface
{
    use Macroable;
    use PendingMailable;

    /**
     * The name that is configured for the mailer.
     */
    protected string $name;

    protected string $views;

    /**
     * The Symfony Transport instance.
     */
    protected TransportInterface $transport;

    /**
     * The event dispatcher instance.
     */
    protected ?EventDispatcherInterface $events;

    /**
     * The global from address and name.
     */
    protected array $from;

    /**
     * The global reply-to address and name.
     */
    protected array $replyTo;

    /**
     * The global return path address.
     */
    protected array $returnPath;

    /**
     * The global to address and name.
     */
    protected array $to;

    /**
     * The queue factory implementation.
     */
    protected string $queue;

    /**
     * Create a new Mailer instance.
     */
    public function __construct(
        string $name,
        TransportInterface $transport,
        protected ContainerInterface $container,
    ) {
        $this->name = $name;
        $this->events = $container->get(EventDispatcherInterface::class);
        $this->transport = $transport;
    }

    /**
     * Set the global from address and name.
     */
    public function alwaysFrom(string $address, string $name = null): void
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Set the global reply-to address and name.
     */
    public function alwaysReplyTo(string $address, ?string $name = null): void
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Set the global return path address.
     */
    public function alwaysReturnPath(string $address): void
    {
        $this->returnPath = compact('address');
    }

    /**
     * Set the global to address and name.
     */
    public function alwaysTo(string $address, ?string $name = null): void
    {
        $this->to = compact('address', 'name');
    }

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function to(mixed $users, string $name = null): PendingMail
    {
        if (! is_null($name) && is_string($users)) {
            $users = new Address($users, $name);
        }

        return (new PendingMail($this))->to($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function cc(mixed $users, string $name = null): PendingMail
    {
        if (! is_null($name) && is_string($users)) {
            $users = new Address($users, $name);
        }

        return (new PendingMail($this))->cc($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     */
    public function bcc(mixed $users, string $name = null): PendingMail
    {
        if (! is_null($name) && is_string($users)) {
            $users = new Address($users, $name);
        }

        return (new PendingMail($this))->bcc($users);
    }

    /**
     * Send a new message with only an HTML part.
     */
    public function html(string $html, mixed $callback): ?SentMessage
    {
        $html = Str::of($html)->toString();
        return $this->send(['html' => $html], [], $callback);
    }

    /**
     * Send a new message with only a raw text part.
     */
    public function raw(string $text, mixed $callback): ?SentMessage
    {
        return $this->send(['raw' => $text], [], $callback);
    }

    /**
     * Send a new message with only a plain part.
     */
    public function plain(string $view, array $data, mixed $callback): ?SentMessage
    {
        return $this->send(['text' => $view], $data, $callback);
    }

    /**
     * Render the given message as a view.
     */
    public function render(array|MailableInterface|string $view, array $data = []): string
    {
        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        [$view, $plain, $raw] = $this->parseView($view);

        $data['message'] = $this->createMessage();

        return $this->replaceEmbeddedAttachments(
            $this->renderView($view ?: $plain, $data),
            $data['message']->getSymfonyMessage()->getAttachments()
        );
    }

    /**
     * Send a new message using a view.
     */
    public function send(
        array|MailableContract|string $view,
        array $data = [],
        \Closure|string $callback = null
    ): ?SentMessage {
        if ($view instanceof MailableContract) {
            return $this->sendMailable($view);
        }
        $data['mailer'] = $this->name;

        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        [$view, $plain, $raw] = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();

        // Once we have retrieved the view content for the e-mail we will set the body
        // of this message using the HTML type, which will provide a simple wrapper
        // to creating view based emails that are able to receive arrays of data.
        if (! is_null($callback)) {
            $callback($message);
        }

        $this->addContent($message, $view, $plain, $raw, $data);

        // If a global "to" address has been set, we will set that address on the mail
        // message. This is primarily useful during local development in which each
        // message should be delivered into a single mail address for inspection.
        if (isset($this->to['address'])) {
            $this->setGlobalToAndRemoveCcAndBcc($message);
        }

        // Next we will determine if the message should be sent. We give the developer
        // one final chance to stop this message and then we will send it to all of
        // its recipients. We will then fire the sent event for the sent message.
        $symfonyMessage = $message->getSymfonyMessage();

        if ($this->shouldSendMessage($symfonyMessage, $data)) {
            $symfonySentMessage = $this->sendSymfonyMessage($symfonyMessage);

            if ($symfonySentMessage) {
                $sentMessage = new SentMessage($symfonySentMessage);

                $this->dispatchSentEvent($sentMessage, $data);

                return $sentMessage;
            }
        }
        return null;
    }

    public function queue($view, ?string $queue = null): bool
    {
        if (! $view instanceof MailableInterface) {
            throw new \InvalidArgumentException('Only mailables may be queued.');
        }

        if (is_string($queue)) {
            $view->onQueue($queue);
        }

        return $view->mailer($this->name)->queue($queue);
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     */
    public function onQueue(string $queue, MailableContract $view): mixed
    {
        return $this->queue($view, $queue);
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     *
     * This method didn't match rest of framework's "onQueue" phrasing. Added "onQueue".
     */
    public function queueOn(string $queue, MailableContract $view): mixed
    {
        return $this->onQueue($queue, $view);
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds.
     *
     * @throws \InvalidArgumentException
     */
    public function later(
        \DateInterval|\DateTimeInterface|int $delay,
        MailableContract $view,
        string $queue = null
    ): mixed {
        if (! $view instanceof MailableContract) {
            throw new \InvalidArgumentException('Only mailables may be queued.');
        }

        return $view->mailer($this->name)->later(
            $delay,
            is_null($queue) ? $this->queue : $queue
        );
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds on the given queue.
     */
    public function laterOn(string $queue, \DateInterval|\DateTimeInterface|int $delay, MailableContract $view): mixed
    {
        return $this->later($delay, $view, $queue);
    }

    /**
     * Get the Symfony Mailer instance.
     */
    public function getSymfonyMailer(): SymfonyMailerInterface
    {
        return $this->mailer;
    }

    /**
     * Set the Symfony Mailer instance.
     */
    public function setSymfonyMailer(SymfonyMailerInterface $mailer): void
    {
        $this->mailer = $mailer;
    }

    /**
     * Get the Symfony Transport instance.
     */
    public function getSymfonyTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Get the view factory instance.
     */
    public function getViewFactory(): string
    {
        return $this->views;
    }

    /**
     * Set the Symfony Transport instance.
     */
    public function setSymfonyTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    /**
     * Set the queue manager instance.
     */
    public function setQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Replace the embedded image attachments with raw, inline image data for browser rendering.
     */
    protected function replaceEmbeddedAttachments(string $renderedView, array $attachments): string
    {
        if (preg_match_all('/<img.+?src=[\'"]cid:([^\'"]+)[\'"].*?>/i', $renderedView, $matches)) {
            foreach (array_unique($matches[1]) as $image) {
                foreach ($attachments as $attachment) {
                    if ($attachment->getFilename() === $image) {
                        $renderedView = str_replace(
                            'cid:' . $image,
                            'data:' . $attachment->getContentType() . ';base64,' . $attachment->bodyToString(),
                            $renderedView
                        );

                        break;
                    }
                }
            }
        }

        return $renderedView;
    }

    /**
     * Determines if the email can be sent.
     */
    protected function shouldSendMessage(Email $message, array $data = []): bool
    {
        if (! $this->events) {
            return true;
        }

        return (bool) $this->events->dispatch(
            new MailMessageSending($message, $data)
        );
    }

    /**
     * Send a Symfony Email instance.
     */
    protected function sendSymfonyMessage(Email $message): ?SymfonySentMessage
    {
        try {
            return $this->transport->send($message, Envelope::create($message));
        } finally {
        }
    }

    /**
     * Dispatch the message sent event.
     */
    protected function dispatchSentEvent(SentMessage $message, array $data = []): void
    {
        if ($this->events) {
            $this->events->dispatch(
                new MailMessageSent($message, $data)
            );
        }
    }

    /**
     * Add the content to a given message.
     */
    protected function addContent(Message $message, string $view = null, string $plain = null, string $raw = null, array $data): void
    {
        if (isset($view)) {
            $message->html($this->renderView($view, $data) ?: ' ');
        }

        if (isset($plain)) {
            $message->text($this->renderView($plain, $data) ?: ' ');
        }

        if (isset($raw)) {
            $message->text($raw);
        }
    }

    /**
     * Render the given view.
     */
    protected function renderView(string $view, array $data): string
    {
        return ApplicationContext::getContainer()->get(RenderInterface::class)->getContents($view, $data);
    }

    /**
     * Parse the given view name or array.
     */
    protected function parseView(array|\Closure|string $view): array
    {
        if (is_string($view) || $view instanceof \Closure) {
            return [$view, null, null];
        }

        // If the given view is an array with numeric keys, we will just assume that
        // both a "pretty" and "plain" view were provided, so we will return this
        // array as is, since it should contain both views with numerical keys.
        if (is_array($view) && isset($view[0])) {
            return [$view[0], $view[1], null];
        }

        // If this view is an array but doesn't contain numeric keys, we will assume
        // the views are being explicitly specified and will extract them via the
        // named keys instead, allowing the developers to use one or the other.
        if (is_array($view)) {
            return [
                $view['html'] ?? null,
                $view['text'] ?? null,
                $view['raw'] ?? null,
            ];
        }

        throw new \InvalidArgumentException('Invalid view.');
    }

    /**
     * Send the given mailable.
     */
    protected function sendMailable(MailableContract $mailable): ?SentMessage
    {
        return $mailable instanceof ShouldQueue
            ? $mailable->mailer($this->name)->queue($this->queue)
            : $mailable->mailer($this->name)->send($this);
    }

    /**
     * Set the global "to" address on the given message.
     */
    protected function setGlobalToAndRemoveCcAndBcc(Message $message): void
    {
        $message->forgetTo();
        $message->to($this->to['address'], $this->to['name'], true);
        $message->forgetCc();
        $message->forgetBcc();
    }

    /**
     * Create a new message instance.
     */
    protected function createMessage(): Message
    {
        $message = new Message(new Email());

        // If a global from address has been specified we will set it on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We'll just go ahead and push this address.
        if (! empty($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        // When a global reply address was specified we will set this on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We will just go ahead and push this address.
        if (! empty($this->replyTo['address'])) {
            $message->replyTo($this->replyTo['address'], $this->replyTo['name']);
        }

        if (! empty($this->returnPath['address'])) {
            $message->returnPath($this->returnPath['address']);
        }

        return $message;
    }
}
