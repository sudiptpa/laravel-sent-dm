<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use SentDm\Core\Exceptions\APIException;
use Sujip\SentDm\SentManager;

class SetupWebhookCommand extends Command
{
    protected $signature = 'sent:setup-webhook
                            {url : The public URL Sent.dm will POST events to}
                            {--connection= : Named connection to use}
                            {--events=* : Event types to subscribe to (defaults to all message events)}';

    protected $description = 'Create a webhook endpoint on the Sent.dm platform';

    /** @var list<string> */
    private const DEFAULT_EVENTS = [
        'message.queued',
        'message.routed',
        'message.sent',
        'message.delivered',
        'message.read',
        'message.failed',
        'message.received',
    ];

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;

        /** @var list<string> $events */
        $events = (array) $this->option('events');
        if (empty($events)) {
            $events = self::DEFAULT_EVENTS;
        }

        $this->components->info("Creating webhook for <comment>{$url}</comment>...");

        try {
            $response = $this->manager->connection($connection)->createWebhook($url, $events);
            $data = $response->data;

            if ($data === null) {
                $this->components->error('API returned empty response.');

                return self::FAILURE;
            }

            $this->components->twoColumnDetail('<fg=green>✓ Webhook created</>', "ID: {$data->id}");
            $this->newLine();

            if ($data->signingSecret !== null) {
                $this->components->twoColumnDetail('Signing secret', "<comment>{$data->signingSecret}</comment>");
                $this->newLine();
                $this->components->info('Add to your <comment>.env</comment>:');
                $this->line("  SENT_WEBHOOK_SECRET={$data->signingSecret}");
                $this->line('  SENT_WEBHOOK_ENABLED=true');
            }
        } catch (APIException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
