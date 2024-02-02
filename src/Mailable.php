<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\Collection\Collection;
use Hyperf\Conditionable\Conditionable;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\CompressInterface;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\UnCompressInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Macroable\Macroable;
use Hyperf\Stringable\Str;
use Hyperf\Support\Traits\ForwardsCalls;
use Hyperf\Testing\Constraint\SeeInOrder;
use Hyperf\View\RenderInterface;
use League\Flysystem\FilesystemException;
use OnixSystemsPHP\HyperfMailer\Contract\Attachable;
use OnixSystemsPHP\HyperfMailer\Contract\HasLocalePreference;
use OnixSystemsPHP\HyperfMailer\Contract\HasMailAddress;
use OnixSystemsPHP\HyperfMailer\Contract\Localizable;
use OnixSystemsPHP\HyperfMailer\Contract\MailableInterface;
use OnixSystemsPHP\HyperfMailer\Contract\MailerInterface;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;
use PHPUnit\Framework\Assert as PHPUnit;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine\Channel;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;

use function Hyperf\Collection\collect;
use function Hyperf\Config\config;
use function Hyperf\Support\call;
use function Hyperf\Support\class_basename;

abstract class Mailable implements MailableInterface
{
    use Conditionable, ForwardsCalls, Macroable, Localizable {
        __call as macroCall;
    }

    /**
     * The locale of the message.
     */
    public string $locale;

    /**
     * The person the message is from.
     */
    public array $from = [];

    /**
     * The "to" recipients of the message.
     */
    public array $to = [];

    /**
     * The "cc" recipients of the message.
     */
    public array $cc = [];

    /**
     * The "bcc" recipients of the message.
     */
    public array $bcc = [];

    /**
     * The "reply to" recipients of the message.
     */
    public array $replyTo = [];

    /**
     * The subject of the message.
     */
    public string $subject;

    /**
     * The Markdown template for the message (if applicable).
     */
    public string $markdown;

    /**
     * The view to use for the message.
     */
    public string $view;

    /**
     * The plain text view to use for the message.
     */
    public string $textView;

    /**
     * The view data for the message.
     */
    public array $viewData = [];

    /**
     * The attachments for the message.
     */
    public array $attachments = [];

    /**
     * The raw attachments for the message.
     */
    public array $rawAttachments = [];

    /**
     * The attachments from a storage adapter.
     */
    public array $storageAttachments = [];

    /**
     * The name of the theme that should be used when formatting the message.
     */
    public ?string $theme;

    /**
     * The name of the mailer that should send the message.
     */
    public string $mailer;

    /**
     * The callbacks for the message.
     */
    public array $callbacks = [];

    /**
     * The callback that should be invoked while building the view data.
     *
     * @var callable
     */
    public static $viewDataCallback;

    /**
     * The tags for the message.
     */
    protected array $tags = [];

    /**
     * The metadata for the message.
     */
    protected array $metadata = [];

    /**
     * The rendered mailable views for testing / assertions.
     */
    protected array $assertionableRenderStrings;

    /**
     * The HTML to use for the message.
     */
    protected string $html;

    public function __construct()
    {
        $config = ApplicationContext::getContainer()->get(ConfigInterface::class);
        $this->locale = $config->get('translation.fallback_locale', 'en-US');
    }

    /**
     * Dynamically bind parameters to the message.
     *
     * @param string $method
     * @param array $parameters
     * @return $this
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (str_starts_with($method, 'with')) {
            return $this->with(Str::camel(substr($method, 4)), $parameters[0]);
        }

        static::throwBadMethodCallException($method);
    }

    public function locale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set the priority of this message.
     *
     * The value is an integer where 1 is the highest priority and 5 is the lowest.
     */
    public function priority(int $level = 3): self
    {
        $this->callbacks[] = function ($message) use ($level) {
            $message->priority($level);
        };

        return $this;
    }

    /**
     * Set the sender of the message.
     */
    public function from(array|object|string $address, string $name = null): self
    {
        return $this->setAddress($address, $name, 'from');
    }

    /**
     * Determine if the given recipient is set on the mailable.
     */
    public function hasFrom(array|object|string $address, string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'from');
    }

    /**
     * Set the recipients of the message.
     */
    public function to(array|object|string $address, string $name = null): self
    {
        if (! $this->locale && $address instanceof HasLocalePreference) {
            $this->locale($address->preferredLocale());
        }

        return $this->setAddress($address, $name, 'to');
    }

    public function replyTo(HasMailAddress|string $address, ?string $name = null): self
    {
        return $this->setAddress($address, $name, 'replyTo');
    }

    public function hasReplyTo(array|object|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'replyTo');
    }

    public function hasTo(HasMailAddress|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'to');
    }

    public function cc(array|object|string $address, string $name = null): self
    {
        return $this->setAddress($address, $name, 'cc');
    }

    public function hasCc(HasMailAddress|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'cc');
    }

    public function bcc(array|object|string $address, ?string $name = null): self
    {
        return $this->setAddress($address, $name, 'bcc');
    }

    public function hasBcc(HasMailAddress|string $address, ?string $name = null): bool
    {
        return $this->hasRecipient($address, $name, 'bcc');
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Determine if the mailable has the given subject.
     */
    public function hasSubject(string $subject): bool
    {
        return $this->subject === $subject
            || (method_exists($this, 'envelope') && $this->envelope()->hasSubject($subject));
    }

    /**
     * Set the Markdown template for the message.
     */
    public function markdown(string $view, array $data = []): static
    {
        $this->markdown = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    /**
     * Set the view and view data for the message.
     */
    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    public function attach(Attachable|Attachment|string $file, array $options = []): self
    {
        if ($file instanceof Attachable) {
            $file = $file->toMailAttachment();
        }

        if ($file instanceof Attachment) {
            return $file->attachTo($this, $options);
        }

        $this->attachments = collect($this->attachments)
            ->push(compact('file', 'options'))
            ->unique('file')
            ->all();

        return $this;
    }

    /**
     * Attach a file to the message from storage.
     *
     * @return $this
     */
    public function attachFromStorageAdapter(string $adapter = null, string $path, string $name = null, array $options = []): static
    {
        $this->storageAttachments = collect($this->storageAttachments)->push([
            'storage' => $adapter ?: config('file.default'),
            'path' => $path,
            'name' => $name ?? basename($path),
            'options' => $options,
        ])->unique(function ($file) {
            return $file['name'] . $file['storage'] . $file['path'];
        })->all();

        return $this;
    }

    /**
     * Attach a file to the message from storage.
     */
    public function attachFromStorage(?string $adapter, string $path, ?string $name = null, array $options = []): self
    {
        return $this->attachFromStorageAdapter(null, $path, $name, $options);
    }

    /**
     * Determine if the mailable has the given attachment from storage.
     */
    public function hasAttachmentFromStorage(string $path, string $name = null, array $options = []): bool
    {
        return $this->hasAttachmentFromStorageAdapter(null, $path, $name, $options);
    }

    /**
     * Determine if the mailable has the given attachment from a specific storage adapter.
     */
    public function hasAttachmentFromStorageAdapter(
        string $adapter = null,
        string $path,
        string $name = null,
        array $options = []
    ): bool {
        return collect($this->storageAttachments)->contains(
            fn ($attachment) => $attachment['storage'] === ($adapter ?? config('file.default'))
                && $attachment['path'] === $path
                && $attachment['name'] === ($name ?? basename($path))
                && $attachment['options'] === $options
        );
    }

    /**
     * Attach multiple files to the message.
     */
    public function attachMany(array $files): static
    {
        foreach ($files as $file => $options) {
            if (is_int($file)) {
                $this->attach($options);
            } else {
                $this->attach($file, $options);
            }
        }

        return $this;
    }

    /**
     * Determine if the mailable has the given attachment.
     */
    public function hasAttachment(Attachable|Attachment|string $file, array $options = []): bool
    {
        if ($file instanceof Attachable) {
            $file = $file->toMailAttachment();
        }

        if ($file instanceof Attachment && $this->hasEnvelopeAttachment($file, $options)) {
            return true;
        }

        if ($file instanceof Attachment) {
            $parts = $file->attachWith(
                fn ($path) => [$path, [
                    'as' => $options['as'] ?? $file->as,
                    'mime' => $options['mime'] ?? $file->mime,
                ]],
                fn ($data) => $this->hasAttachedData($data(), $options['as'] ?? $file->as, ['mime' => $options['mime'] ?? $file->mime])
            );

            if ($parts === true) {
                return true;
            }

            [$file, $options] = $parts === false
                ? [null, []]
                : $parts;
        }

        return collect($this->attachments)->contains(
            fn ($attachment) => $attachment['file'] === $file && array_filter($attachment['options']) === array_filter($options)
        );
    }

    public function attachData(string $data, string $name, array $options = []): self
    {
        $this->rawAttachments = collect($this->rawAttachments)
            ->push(compact('data', 'name', 'options'))
            ->unique(function ($file) {
                return $file['name'] . $file['data'];
            })->all();

        return $this;
    }

    /**
     * Determine if the mailable has the given data as an attachment.
     */
    public function hasAttachedData(string $data, string $name, array $options = []): bool
    {
        return collect($this->rawAttachments)->contains(
            fn ($attachment) => $attachment['data'] === $data
                && $attachment['name'] === $name
                && array_filter($attachment['options']) === array_filter($options)
        );
    }

    /**
     * Add a tag header to the message when supported by the underlying transport.
     */
    public function tag(string $value): static
    {
        array_push($this->tags, $value);

        return $this;
    }

    /**
     * Determine if the mailable has the given tag.
     */
    public function hasTag(string $value): bool
    {
        return in_array($value, $this->tags)
            || (method_exists($this, 'envelope') && in_array($value, $this->envelope()->tags));
    }

    /**
     * Add a metadata header to the message when supported by the underlying transport.
     */
    public function metadata(string $key, string $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Determine if the mailable has the given metadata.
     */
    public function hasMetadata(string $key, string $value): bool
    {
        return (isset($this->metadata[$key]) && $this->metadata[$key] === $value)
            || (method_exists($this, 'envelope') && $this->envelope()->hasMetadata($key, $value));
    }

    /**
     * Assert that the mailable is from the given address.
     */
    public function assertFrom(array|object|string $address, string $name = null): self
    {
        $this->renderForAssertions();

        $recipient = $this->formatAssertionRecipient($address, $name);

        PHPUnit::assertTrue(
            $this->hasFrom($address, $name),
            "Email was not from expected address [{$recipient}]."
        );

        return $this;
    }

    /**
     * Assert that the mailable has the given subject.
     */
    public function assertHasSubject(string $subject): static
    {
        $this->renderForAssertions();

        PHPUnit::assertTrue(
            $this->hasSubject($subject),
            "Did not see expected text [{$subject}] in email subject."
        );

        return $this;
    }

    /**
     * Assert that the mailable has the given recipient.
     */
    public function assertTo(array|object|string $address, string $name = null): static
    {
        $this->renderForAssertions();

        $recipient = $this->formatAssertionRecipient($address, $name);

        PHPUnit::assertTrue(
            $this->hasTo($address, $name),
            "Did not see expected recipient [{$recipient}] in email 'to' recipients."
        );

        return $this;
    }

    /**
     * Assert that the given text is present in the HTML email body.
     */
    public function assertSeeInHtml(string $string, bool $escape = true): static
    {
        $string = $escape ? htmlspecialchars($string, ENT_QUOTES, 'UTF-8') : $string;

        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertStringContainsString(
            $string,
            $html,
            "Did not see expected text [{$string}] within email body."
        );

        return $this;
    }

    /**
     * Assert that the given text is not present in the HTML email body.
     */
    public function assertDontSeeInHtml(string $string, bool $escape = true): static
    {
        $string = $escape ? htmlspecialchars($string, ENT_QUOTES, 'UTF-8') : $string;

        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertStringNotContainsString(
            $string,
            $html,
            "Saw unexpected text [{$string}] within email body."
        );

        return $this;
    }

    /**
     * Assert that the given text strings are present in order in the HTML email body.
     */
    public function assertSeeInOrderInHtml(array $strings, bool $escape = true): static
    {
        $strings = $escape ? array_map('htmlspecialchars', $strings, array_fill(0, count($strings), ENT_QUOTES)) : $strings;

        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertThat($strings, new SeeInOrder($html));

        return $this;
    }

    /**
     * Assert that the given text is present in the plain-text email body.
     */
    public function assertSeeInText(string $string): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertStringContainsString(
            $string,
            $text,
            "Did not see expected text [{$string}] within text email body."
        );

        return $this;
    }

    /**
     * Assert that the given text is not present in the plain-text email body.
     */
    public function assertDontSeeInText(string $string): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertStringNotContainsString(
            $string,
            $text,
            "Saw unexpected text [{$string}] within text email body."
        );

        return $this;
    }

    /**
     * Assert that the given text strings are present in order in the plain-text email body.
     */
    public function assertSeeInOrderInText(array $strings): static
    {
        [$html, $text] = $this->renderForAssertions();

        PHPUnit::assertThat($strings, new SeeInOrder($text));

        return $this;
    }

    /**
     * Assert the mailable has the given attachment.
     */
    public function assertHasAttachment(Attachable|Attachment|string $file, array $options = []): static
    {
        $this->renderForAssertions();

        PHPUnit::assertTrue(
            $this->hasAttachment($file, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * Assert the mailable has the given data as an attachment.
     */
    public function assertHasAttachedData(string $data, string $name, array $options = []): static
    {
        $this->renderForAssertions();

        PHPUnit::assertTrue(
            $this->hasAttachedData($data, $name, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * Assert the mailable has the given attachment from storage.
     */
    public function assertHasAttachmentFromStorage(string $path, string $name = null, array $options = []): static
    {
        $this->renderForAssertions();

        PHPUnit::assertTrue(
            $this->hasAttachmentFromStorage($path, $name, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * Assert the mailable has the given attachment from a specific storage disk.
     */
    public function assertHasAttachmentFromStorageDisk(string $disk, string $path, string $name = null, array $options = []): static
    {
        $this->renderForAssertions();

        PHPUnit::assertTrue(
            $this->hasAttachmentFromStorageDisk($disk, $path, $name, $options),
            'Did not find the expected attachment.'
        );

        return $this;
    }

    /**
     * Assert that the mailable has the given tag.
     */
    public function assertHasTag(string $tag): static
    {
        $this->renderForAssertions();

        PHPUnit::assertTrue(
            $this->hasTag($tag),
            "Did not see expected tag [{$tag}] in email tags."
        );

        return $this;
    }

    /**
     * Assert that the mailable has the given metadata.
     */
    public function assertHasMetadata(string $key, string $value): static
    {
        $this->renderForAssertions();

        PHPUnit::assertTrue(
            $this->hasMetadata($key, $value),
            "Did not see expected key [{$key}] and value [{$value}] in email metadata."
        );

        return $this;
    }

    public function mailer(string $mailer): self
    {
        $this->mailer = $mailer;

        return $this;
    }

    /**
     * Register a callback to be called while building the view data.
     */
    public static function buildViewDataUsing(callable $callback): void
    {
        static::$viewDataCallback = $callback;
    }

    public function with(array|string $key, mixed $value = null): self
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } elseif (is_string($key)) {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * Set the rendered HTML content for the message.
     */
    public function html(string $html): static
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Set the plain text view for the message.
     */
    public function text(string $textView, array $data = []): static
    {
        $this->textView = $textView;
        $this->viewData = array_merge($this->viewData, $data);

        return $this;
    }

    public function render($mailer = null): string
    {
        $mailer = $this->resolveMailer($mailer);

        return $mailer->render($this);
    }

    /**
     * Send the message using the given mailer.
     */
    public function send(MailerInterface|MailManagerInterface $mailer): ?SentMessage
    {
        return $this->withLocale($this->locale, function () use ($mailer) {
            $this->prepareMailableForDelivery();

            $mailer = $mailer instanceof MailableInterface
                ? $mailer->mailer($this->mailer)
                : $mailer;

            $buildViewData = $this->buildViewData();

            return $mailer->send($this->buildView($buildViewData), $buildViewData, function ($message) {
                $this->buildFrom($message)
                    ->buildRecipients($message)
                    ->buildSubject($message)
                    ->buildTags($message)
                    ->buildMetadata($message)
                    ->runCallbacks($message)
                    ->buildAttachments($message);
            });
        });
    }

    public function queue(?string $queue = null): bool
    {
        $queue = $queue ?: (property_exists($this, 'queue') ? $this->queue : array_key_first(config('async_queue')));

        return ApplicationContext::getContainer()->get(DriverFactory::class)->get($queue)->push($this->newQueuedJob());
    }

    public function later(\DateInterval|\DateTimeInterface|int $delay, ?string $queue = null): bool
    {
        $queue = $queue ?: (property_exists($this, 'queue') ? $this->queue : array_key_first(config('async_queue')));

        return ApplicationContext::getContainer()->get(DriverFactory::class)->get($queue)->push($this->newQueuedJob(), $delay);
    }

    public function uncompress(): self
    {
        foreach ($this as $key => $value) {
            if ($value instanceof UnCompressInterface) {
                $this->{$key} = $value->uncompress();
            }
        }

        return $this;
    }

    public function compress(): self
    {
        foreach ($this as $key => $value) {
            if ($value instanceof CompressInterface) {
                $this->{$key} = $value->compress();
            }
        }

        return $this;
    }

    public function buildViewData(): array
    {
        $data = $this->viewData;

        if (static::$viewDataCallback) {
            $data = array_merge($data, call_user_func(static::$viewDataCallback, $this));
        }

        foreach ((new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== self::class) {
                $data[$property->getName()] = $property->getValue($this);
            }
        }

        return $data;
    }

    /**
     * Register a callback to be called with the Symfony message instance.
     */
    public function withSymfonyMessage(callable $callback): static
    {
        $this->callbacks[] = $callback;

        return $this;
    }

    protected function resolveMailer(null|MailerInterface|MailManagerInterface $mailer = null): MailerInterface
    {
        return empty($mailer)
            ? ApplicationContext::getContainer()->get(MailManagerInterface::class)->mailer($this->mailer)
            : ($mailer instanceof MailManager ? $mailer->mailer($this->mailer) : $mailer);
    }

    /**
     * Render the HTML and plain-text version of the mailable into views for assertions.
     * @throws \ReflectionException
     */
    protected function renderForAssertions(): array
    {
        if ($this->assertionableRenderStrings) {
            return $this->assertionableRenderStrings;
        }

        return $this->assertionableRenderStrings = $this->withLocale($this->locale, function () {
            $this->prepareMailableForDelivery();

            $mailable = clone $this;
            call([$mailable, 'build']);

            $data = $mailable->buildViewData();
            $view = $this->buildView($data);
            $html = $this->render();

            if (is_array($view) && isset($view[1])) {
                $text = $view[1];
            }

            $text ??= $view['text'] ?? '';

            if (! empty($text) && ! is_string($text)) {
                $mailerInstance = ApplicationContext::getContainer()->get(Mailer::class);
                $text = $mailerInstance->render($text, $this->buildViewData());
            }

            return [(string) $html, (string) $text];
        });
    }

    /**
     * Prepare the mailable instance for delivery.
     */
    protected function prepareMailableForDelivery(): void
    {
        if (method_exists($this, 'build')) {
            $mailable = clone $this;
            call([$mailable, 'build']);
        }

        $this->ensureHeadersAreHydrated();
        $this->ensureEnvelopeIsHydrated();
        $this->ensureContentIsHydrated();
        $this->ensureAttachmentsAreHydrated();
    }

    /**
     * Set the recipients of the message.
     *
     * All recipients are stored internally as [['name' => ?, 'address' => ?]]
     */
    protected function setAddress(array|object|string $address, string $name = null, string $property = 'to'): static
    {
        if (empty($address)) {
            return $this;
        }

        foreach ($this->addressesToArray($address, $name) as $recipient) {
            $recipient = $this->normalizeRecipient($recipient);

            $this->{$property}[] = [
                'name' => $recipient->name ?? null,
                'address' => $recipient->email,
            ];
        }

        $this->{$property} = collect($this->{$property})
            ->reverse()
            ->unique('address')
            ->reverse()
            ->values()
            ->all();

        return $this;
    }

    /**
     * Convert the given recipient arguments to an array.
     */
    protected function addressesToArray(array|object|string $address, ?string $name): array
    {
        if (! is_array($address) && ! $address instanceof Collection) {
            $address = is_string($name) ? [['name' => $name, 'email' => $address]] : [$address];
        }

        return $address;
    }

    /**
     * Make the queued mailable job instance.
     */
    protected function newQueuedJob(): QueuedMailableJob
    {
        return new QueuedMailableJob($this);
    }

    protected function addRecipient(
        array|Collection|HasMailAddress|string $address,
        ?string $name = null,
        string $property = 'to',
    ): self {
        $this->{$property} = array_merge($this->{$property}, $this->arrayizeAddress($address, $name));

        return $this;
    }

    /**
     * Convert the given recipient arguments to an array.
     */
    protected function arrayizeAddress(array|Collection|HasMailAddress|string $address, ?string $name = null): array
    {
        $addresses = [];
        if (is_array($address) || $address instanceof Collection) {
            foreach ($address as $item) {
                if (is_array($item) && isset($item['address'])) {
                    $addresses[] = [
                        'address' => $item['address'],
                        'name' => $item['name'] ?? null,
                    ];
                } elseif (is_string($item) || $item instanceof HasMailAddress) {
                    $addresses[] = $this->normalizeRecipient($item);
                }
            }
        } else {
            $addresses[] = $this->normalizeRecipient($address, $name);
        }
        return $addresses;
    }

    /**
     * Convert the given recipient into an object.
     */
    protected function normalizeRecipient(mixed $recipient): object
    {
        if (is_array($recipient)) {
            if (array_values($recipient) === $recipient) {
                return (object) array_map(function ($email) {
                    return compact('email');
                }, $recipient);
            }

            return (object) $recipient;
        }
        if (is_string($recipient)) {
            return (object) ['email' => $recipient];
        }
        if ($recipient instanceof Address) {
            return (object) ['email' => $recipient->getAddress(), 'name' => $recipient->getName()];
        }
        if ($recipient instanceof Mailables\Address) {
            return (object) ['email' => $recipient->address, 'name' => $recipient->name];
        }

        return $recipient;
    }

    /**
     * Determine if the given recipient is set on the mailable.
     */
    protected function hasRecipient(
        HasMailAddress|string $address,
        ?string $name = null,
        string $property = 'to',
    ): bool {
        if (empty($address)) {
            return false;
        }

        $expected = $this->normalizeRecipient(
            $this->addressesToArray($address, $name)[0]
        );

        $expected = [
            'name' => $expected->name ?? null,
            'address' => $expected->email,
        ];

        if ($this->hasEnvelopeRecipient($expected['address'], $expected['name'], $property)) {
            return true;
        }

        return collect($this->{$property})->contains(function ($actual) use ($expected) {
            if (! isset($expected['name'])) {
                return $actual['address'] == $expected['address'];
            }

            return $actual == $expected;
        });
    }

    protected function buildView(array $data): ?string
    {
        $channel = new Channel(1);

        $result = null;

        Coroutine::create(function () use ($data, $channel) {
            if (! empty($this->locale)) {
                ApplicationContext::getContainer()->get(TranslatorInterface::class)->setLocale($this->locale);
            }

            if (isset($this->html)) {
                $html = $this->renderView($this->html, $data);
                $result = array_filter([
                    'html' => $html,
                    'text' => $this->textView ?? null,
                ]);
                $channel->push($result);
                return;
            }

            if (isset($this->markdown)) {
                $result = $this->buildMarkdownView();
                $channel->push($result);
                return;
            }

            if (isset($this->view, $this->textView)) {
                $result = [$this->view, $this->textView];
                $channel->push($result);
                return;
            } elseif (isset($this->textView)) {
                $result = ['text' => $this->textView];
                $channel->push($result);
                return;
            }

            $channel->push($this->view);
        });

        $result = $channel->pop();

        return is_null($result) ? $this->view : $result;
    }

    protected function buildMarkdownView(): array
    {
        $data = $this->buildViewData();

        return [
            'html' => $this->buildMarkdownHtml($data),
            'text' => $this->buildMarkdownText($data),
        ];
    }

    /**
     * Build the text view for a Markdown message.
     */
    protected function buildMarkdownText(array $viewData): \Closure
    {
        return function ($data) use ($viewData) {
            if (isset($data['message'])) {
                $data = array_merge($data, [
                    'message' => new TextMessage($data['message']),
                ]);
            }

            return $this->textView ?? $this->renderView(
                $this->markdown,
                array_merge($data, $viewData)
            );
        };
    }

    /**
     * Build the HTML view for a Markdown message.
     */
    protected function buildMarkdownHtml(array $viewData): \Closure
    {
        return fn ($data) => $this->renderView(
            $this->markdown,
            array_merge($data, $viewData),
        );
    }

    /**
     * Add the sender to the message.
     */
    protected function buildFrom(Message $message): static
    {
        if (! empty($this->from)) {
            $message->from($this->from[0]['address'], $this->from[0]['name']);
        }

        return $this;
    }

    /**
     * Add all of the recipients to the message.
     * @return $this
     */
    protected function buildRecipients(Message $message): static
    {
        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{$type}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * Render the given view.
     */
    protected function renderView(string $view, array $data): string
    {
        return ApplicationContext::getContainer()->get(RenderInterface::class)->getContents($view, $data);
    }

    /**
     * Add all of the addresses to the message.
     */
    protected function buildAddresses(Message $message): self
    {
        foreach (['from', 'replyTo'] as $type) {
            isset($this->{$type})
            && is_array($this->{$type})
            && $message->{'set' . ucfirst($type)}($this->{$type}['address'], $this->{$type}['name']);
        }

        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ($this->{$type} as $recipient) {
                $message->{'set' . ucfirst($type)}($recipient['address'], $recipient['name']);
            }
        }

        return $this;
    }

    /**
     * Set the subject for the message.
     */
    protected function buildSubject(Message $message): self
    {
        if ($this->subject) {
            $message->subject($this->subject);
        } else {
            $message->subject(Str::title(Str::snake(class_basename($this), ' ')));
        }

        return $this;
    }

    /**
     * Add all of the attachments to the message.
     *
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws FilesystemException
     */
    protected function buildAttachments(Message $message): self
    {
        foreach ($this->attachments as $attachment) {
            $message->attach($attachment['file'], $attachment['options']);
        }

        foreach ($this->rawAttachments as $attachment) {
            $message->attachData(
                $attachment['data'],
                $attachment['name'],
                $attachment['options']
            );
        }

        // Add all of the adapter attachments to the message.
        foreach ($this->storageAttachments as $attachment) {
            $storage = ApplicationContext::getContainer()->get(FilesystemFactory::class)->get($attachment['storage']);

            $message->attachData(
                $storage->read($attachment['path']),
                $attachment['name'] ?? basename($attachment['path']),
                array_merge(['mime' => $storage->mimetype($attachment['path'])], $attachment['options'])
            );
        }

        return $this;
    }

    /**
     * Add all defined tags to the message.
     */
    protected function buildTags(Message $message): static
    {
        if ($this->tags) {
            foreach ($this->tags as $tag) {
                $message->getHeaders()->add(new TagHeader($tag));
            }
        }

        return $this;
    }

    /**
     * Add all defined metadata to the message.
     */
    protected function buildMetadata(Message $message): static
    {
        if ($this->metadata) {
            foreach ($this->metadata as $key => $value) {
                $message->getHeaders()->add(new MetadataHeader($key, $value));
            }
        }

        return $this;
    }

    /**
     * Add the content to a given message.
     */
    protected function buildContents(Message $message, ?string $html, ?string $plain, array $data): self
    {
        if (! empty($html)) {
            $message->html($html);
        }

        if (! empty($plain)) {
            if (empty($html)) {
                $message->text($plain);
            } else {
                $message->attach($plain, null, $plain);
            }
        }

        $message->setData($data);

        return $this;
    }

    /**
     * Run the callbacks for the message.
     *
     * @return $this
     */
    protected function runCallbacks(Message $message): self
    {
        foreach ($this->callbacks as $callback) {
            $callback($message->getSymfonyMessage());
        }

        return $this;
    }

    /**
     * Format the mailable recipient for display in an assertion message.
     */
    private function formatAssertionRecipient(array|object|string $address, string $name = null): string
    {
        if (! is_string($address)) {
            $address = json_encode($address);
        }

        if (! empty($name)) {
            $address .= ' (' . $name . ')';
        }

        return $address;
    }

    /**
     * Ensure the mailable's attachments are hydrated from the "attachments" method.
     */
    private function ensureAttachmentsAreHydrated(): void
    {
        if (! method_exists($this, 'attachments')) {
            return;
        }

        $attachments = $this->attachments();

        Collection::make(is_object($attachments) ? [$attachments] : $attachments)
            ->each(function ($attachment) {
                $this->attach($attachment);
            });
    }

    /**
     * Ensure the mailable's content is hydrated from the "content" method.
     */
    private function ensureContentIsHydrated(): void
    {
        if (! method_exists($this, 'content')) {
            return;
        }

        $content = $this->content();

        if ($content->view) {
            $this->view($content->view);
        }

        if ($content->html) {
            $this->view($content->html);
        }

        if ($content->text) {
            $this->text($content->text);
        }

        if ($content->markdown) {
            $this->markdown($content->markdown);
        }

        if ($content->htmlString) {
            $this->html($content->htmlString);
        }

        foreach ($content->with as $key => $value) {
            $this->with($key, $value);
        }
    }

    /**
     * Ensure the mailable's "envelope" data is hydrated from the "envelope" method.
     */
    private function ensureEnvelopeIsHydrated(): void
    {
        if (! method_exists($this, 'envelope')) {
            return;
        }

        $envelope = $this->envelope();

        if (isset($envelope->from)) {
            $this->from($envelope->from->address, $envelope->from->name);
        }

        foreach (['to', 'cc', 'bcc', 'replyTo'] as $type) {
            foreach ($envelope->{$type} as $address) {
                $this->{$type}($address->address, $address->name);
            }
        }

        if ($envelope->subject) {
            $this->subject($envelope->subject);
        }

        foreach ($envelope->tags as $tag) {
            $this->tag($tag);
        }

        foreach ($envelope->metadata as $key => $value) {
            $this->metadata($key, $value);
        }

        foreach ($envelope->using as $callback) {
            $this->withSymfonyMessage($callback);
        }
    }

    /**
     * Ensure the mailable's headers are hydrated from the "headers" method.
     */
    private function ensureHeadersAreHydrated(): void
    {
        if (! method_exists($this, 'headers')) {
            return;
        }

        $headers = $this->headers();

        $this->withSymfonyMessage(function ($message) use ($headers) {
            if ($headers->messageId) {
                $message->getHeaders()->addIdHeader('Message-Id', $headers->messageId);
            }

            if (count($headers->references) > 0) {
                $message->getHeaders()->addTextHeader('References', $headers->referencesString());
            }

            foreach ($headers->text as $key => $value) {
                $message->getHeaders()->addTextHeader($key, $value);
            }
        });
    }

    /**
     * Determine if the mailable has the given envelope attachment.
     */
    private function hasEnvelopeAttachment(Attachment $attachment, array $options = []): bool
    {
        if (! method_exists($this, 'envelope')) {
            return false;
        }

        $attachments = $this->attachments();

        return Collection::make(is_object($attachments) ? [$attachments] : $attachments)
            ->map(fn ($attached) => $attached instanceof Attachable ? $attached->toMailAttachment() : $attached)
            ->contains(fn ($attached) => $attached->isEquivalent($attachment, $options));
    }

    /**
     * Determine if the mailable "envelope" method defines a recipient.
     */
    private function hasEnvelopeRecipient(string $address, ?string $name, string $property): bool
    {
        return method_exists($this, 'envelope') && match ($property) {
            'from' => $this->envelope()->isFrom($address, $name),
            'to' => $this->envelope()->hasTo($address, $name),
            'cc' => $this->envelope()->hasCc($address, $name),
            'bcc' => $this->envelope()->hasBcc($address, $name),
            'replyTo' => $this->envelope()->hasReplyTo($address, $name),
        };
    }
}
