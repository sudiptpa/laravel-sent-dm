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
                            {--name= : Display name for the webhook (defaults to the URL\'s host)}
                            {--connection= : Named connection to use}
                            {--events=* : Top-level event categories to subscribe to: message, templates (defaults to message)}';

    protected $description = 'Create a webhook endpoint on the Sent.dm platform';

    /**
     * Top-level categories only (message, templates). Granular names like message.sent
     * are sub-types delivered in the payload, not values you subscribe with.
     *
     * @var list<string>
     */
    private const DEFAULT_EVENTS = ['message'];

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;

        $name = $this->option('name');
        $name = is_string($name) && $name !== '' ? $name : (parse_url($url, PHP_URL_HOST) ?: $url);

        /** @var list<string> $events */
        $events = (array) $this->option('events');
        if (empty($events)) {
            $events = self::DEFAULT_EVENTS;
        }

        $this->components->info("Creating webhook for <comment>{$url}</comment>...");

        try {
            $response = $this->manager->connection($connection)->webhooks()->create()
                ->name($name)
                ->url($url)
                ->events($events)
                ->save();
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
