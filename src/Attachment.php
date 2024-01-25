<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Context\ApplicationContext;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\Macroable\Macroable;
use function Hyperf\Support\with;

class Attachment
{
    use Macroable;

    /**
     * The attached file's filename.
     */
    public ?string $as;

    /**
     * The attached file's mime type.
     */
    public ?string $mime;

    private function __construct(private readonly \Closure $resolver) {}

    /**
     * Create a mail attachment from a path.
     */
    public static function fromPath(string $path): static
    {
        return new static(fn ($attachment, $pathStrategy) => $pathStrategy($path, $attachment));
    }

    /**
     * Create a mail attachment from in-memory data.
     */
    public static function fromData(\Closure $data, string $name = null): static
    {
        return (new static(
            fn ($attachment, $pathStrategy, $dataStrategy) => $dataStrategy($data, $attachment)
        ))->as($name);
    }

    /**
     * Create a mail attachment from a file in the default storage disk.
     */
    public static function fromStorage(string $path): static
    {
        return static::и(null, $path);
    }

    /**
     * Create a mail attachment from a file in the specified storage disk.
     */
    public static function fromStorageDisk(?string $disk, string $path): static
    {
        return new static(function ($attachment, $pathStrategy, $dataStrategy) use ($disk, $path) {
            $storage = ApplicationContext::getContainer()->get(
                FilesystemFactory::class
            )->disk($disk);

            $attachment
                ->as($attachment->as ?? basename($path))
                ->withMime($attachment->mime ?? $storage->mimeType($path));

            return $dataStrategy(fn () => $storage->get($path), $attachment);
        });
    }

    /**
     * Set the attached file's filename.
     *
     * @return $this
     */
    public function as(?string $name): static
    {
        $this->as = $name;

        return $this;
    }

    /**
     * Set the attached file's mime type.
     *
     * @return $this
     */
    public function withMime(string $mime): static
    {
        $this->mime = $mime;

        return $this;
    }

    /**
     * Attach the attachment with the given strategies.
     */
    public function attachWith(\Closure $pathStrategy, \Closure $dataStrategy): mixed
    {
        return ($this->resolver)($this, $pathStrategy, $dataStrategy);
    }

    /**
     * Attach the attachment to a built-in mail type.
     */
    public function attachTo(Mailable|Message $mail, array $options = []): mixed
    {
        return $this->attachWith(
            fn ($path) => $mail->attach($path, [
                'as' => $options['as'] ?? $this->as,
                'mime' => $options['mime'] ?? $this->mime,
            ]),
            function ($data) use ($mail, $options) {
                $options = [
                    'as' => $options['as'] ?? $this->as,
                    'mime' => $options['mime'] ?? $this->mime,
                ];

                if ($options['as'] === null) {
                    throw new \RuntimeException('Attachment requires a filename to be specified.');
                }

                return $mail->attachData($data(), $options['as'], ['mime' => $options['mime']]);
            }
        );
    }

    /**
     * Determine if the given attachment is equivalent to this attachment.
     */
    public function isEquivalent(Attachment $attachment, array $options = []): bool
    {
        return with([
            'as' => $options['as'] ?? $attachment->as,
            'mime' => $options['mime'] ?? $attachment->mime,
        ], fn ($options) => $this->attachWith(
            fn ($path) => [$path, ['as' => $this->as, 'mime' => $this->mime]],
            fn ($data) => [$data(), ['as' => $this->as, 'mime' => $this->mime]],
        ) === $attachment->attachWith(
            fn ($path) => [$path, $options],
            fn ($data) => [$data(), $options],
        ));
    }
}
