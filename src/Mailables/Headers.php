<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Mailables;

use Hyperf\Conditionable\Conditionable;
use Hyperf\Stringable\Str;

use function Hyperf\Collection\collect;

class Headers
{
    use Conditionable;

    public function __construct(
        public ?string $messageId = null,
        public array $references = [],
        public array $text = []
    ) {}

    /**
     * @param string $messageId
     * @return $this
     */
    public function messageId(string $messageId): Headers
    {
        $this->messageId = $messageId;

        return $this;
    }

    /**
     * @param array $references
     * @return $this
     */
    public function references(array $references): Headers
    {
        $this->references = array_merge($this->references, $references);

        return $this;
    }

    /**
     * @param array $text
     * @return $this
     */
    public function text(array $text): Headers
    {
        $this->text = array_merge($this->text, $text);

        return $this;
    }

    /**
     * @return string
     */
    public function referencesString(): string
    {
        return collect($this->references)->map(function ($messageId) {
            return Str::finish(Str::start($messageId, '<'), '>');
        })->implode('');
    }
}
