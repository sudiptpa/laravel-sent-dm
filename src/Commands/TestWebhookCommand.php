<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use SentDm\Core\Exceptions\APIException;
use Sujip\SentDm\SentManager;

class TestWebhookCommand extends Command
{
    protected $signature = 'sent:webhook:test
                            {id : Webhook ID to test}
                            {--event=message.sent : Event type to send}
                            {--connection= : Named connection to use}
                            {--idempotency-key= : Optional Idempotency-Key header value}
                            {--sandbox : Simulate without side effects}';

    protected $description = 'Send a test event to a Sent.dm webhook endpoint';

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $event = $this->option('event');
        $event = is_string($event) && $event !== '' ? $event : 'message.sent';
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;
        $idempotencyKey = $this->option('idempotency-key');
        $idempotencyKey = is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null;

        $this->components->info("Sending <comment>{$event}</comment> test event to webhook <comment>{$id}</comment>...");

        try {
            $response = $this->manager->connection($connection)->webhooks()->test(
                $id,
                $event,
                $idempotencyKey,
                (bool) $this->option('sandbox'),
            );
        } catch (APIException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $data = $response->data;
        if ($data === null) {
            $this->components->error('API returned empty response.');

            return self::FAILURE;
        }

        $status = $data->success === false ? '<fg=yellow>Test sent, endpoint reported an issue</>' : '<fg=green>✓ Test event sent</>';
        $this->components->twoColumnDetail($status);

        if (is_string($data->message) && $data->message !== '') {
            $this->components->twoColumnDetail('Message', $data->message);
        }

        return self::SUCCESS;
    }
}
