<?php

declare(strict_types=1);

namespace Sujip\SentDm\Events;

use Sujip\SentDm\Webhooks\WebhookPayload;

/** Fired from the `templates.category_updated` webhook. A template category changed, which changes how it is priced. */
final readonly class TemplateCategoryUpdated
{
    public function __construct(public WebhookPayload $payload) {}
}
