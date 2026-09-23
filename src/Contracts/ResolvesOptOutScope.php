<?php

declare(strict_types=1);

namespace Sujip\SentDm\Contracts;

use Sujip\SentDm\Messages\SentMessage;
use Sujip\SentDm\Webhooks\WebhookPayload;

interface ResolvesOptOutScope
{
    public function forMessage(SentMessage $message, string $connection): string;

    public function forWebhook(WebhookPayload $payload): string;
}
