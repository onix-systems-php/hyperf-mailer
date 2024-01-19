<?php

namespace OnixSystemsPHP\HyperfMailer\Contract;

use OnixSystemsPHP\HyperfMailer\Attachment;

interface Attachable
{
    /**
     * Get an attachment instance for this entity.
     *
     * @return \OnixSystemsPHP\HyperfMailer\Attachment
     */
    public function toMailAttachment(): Attachment;
}
