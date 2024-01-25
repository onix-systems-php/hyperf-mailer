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

    /**
     * The Blade view that should be rendered for the mailable.
     */
    public ?string $view;

    /**
     * The Blade view that should be rendered for the mailable.
     *
     * Alternative syntax for "view".
     */
    public ?string $html;

    /**
     * The Blade view that represents the text version of the message.
     */
    public ?string $text;

    /**
     * The Blade view that represents the Markdown version of the message.
     */
    public ?string $markdown;

    /**
     * The pre-rendered HTML of the message.
     */
    public ?string $htmlString;

    /**
     * The message's view data.
     */
    public array $with;

    /**
     * Create a new content definition.
     *
     * @named-arguments-supported
     */
    public function __construct(
        string $view = null,
        string $html = null,
        string $text = null,
        string $markdown = null,
        array $with = [],
        string $htmlString = null
    ) {
        $this->view = $view;
        $this->html = $html;
        $this->text = $text;
        $this->markdown = $markdown;
        $this->with = $with;
        $this->htmlString = $htmlString;
    }

    /**
     * Set the view for the message.
     *
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
     * @return $this
     */
    public function html(string $view): Content
    {
        return $this->view($view);
    }

    /**
     * Set the plain text view for the message.
     *
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
     * Set the pre-rendered HTML for the message.
     */
    public function htmlString(string $html): Content
    {
        $this->htmlString = $html;

        return $this;
    }
}
