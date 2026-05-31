<?php

declare(strict_types=1);

namespace Sujip\SentDm\Tests;

class WebhookTestCase extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('sent.webhook.enabled', true);
        $app['config']->set('sent.webhook.secret', 'whsec_'.base64_encode('test-webhook-secret'));
        $app['config']->set('sent.webhook.path', 'sent/webhook');
    }
}
