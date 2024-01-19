<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer\Mailables;

use Hyperf\Conditionable\Conditionable;

class Content
{
    use Conditionable;

    public function __construct(
        public ?string $view = null,
        public ?string $html = null,
        private ?string $text = null,
        private ?string $markdown = null,
        private array $with = [],
        public ?string $htmlString = null,
    ) {}

    /**
     * Set the view for the message.
     *
     * @param string $view
     * @return $this
     */
    public function view(string $view): Content
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Set the view for the message.
     *
     * @param string $view
     * @return $this
     */
    public function html(string $view): Content
    {
        return $this->view($view);
    }

    /**
     * Set the plain text view for the message.
     *
     * @param string $view
     * @return $this
     */
    public function text(string $view): Content
    {
        $this->text = $view;

        return $this;
    }

    /**
     * Set the Markdown view for the message.
     *
     * @return $this
     */
    public function markdown(string $view): Content
    {
        $this->markdown = $view;

        return $this;
    }

    /**
     * Add a piece of view data to the message.
     */
    public function with(array|string $key, mixed $value = null): Content
    {
        if (is_array($key)) {
            $this->with = array_merge($this->with, $key);
        } else {
            $this->with[$key] = $value;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function htmlString(string $html): Content
    {
        $this->htmlString = $html;

        return $this;
    }
}
