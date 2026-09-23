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
                            {--secret-file= : New local environment file for the signing secret}
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

        $secretPath = $this->option('secret-file');
        $secretPath = is_string($secretPath) && $secretPath !== ''
            ? $secretPath
            : storage_path('app/private/sent-webhook.env');

        if (str_contains($secretPath, '://')) {
            $this->components->error('The secret file must be a local path.');

            return self::FAILURE;
        }

        $permissions = umask(0077);
        try {
            $directory = dirname($secretPath);
            if (! is_dir($directory) && ! @mkdir($directory, 0700, true)) {
                $this->components->error('The secret directory could not be created.');

                return self::FAILURE;
            }

            $secretFile = @fopen($secretPath, 'x');
        } finally {
            umask($permissions);
        }

        if ($secretFile === false) {
            $this->components->error('Choose a writable secret file path that does not already exist.');

            return self::FAILURE;
        }

        $secretSaved = false;
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
                $contents = 'SENT_WEBHOOK_SECRET='.json_encode($data->signingSecret, JSON_THROW_ON_ERROR).PHP_EOL;

                if (@fwrite($secretFile, $contents) !== strlen($contents)) {
                    $this->components->error('The signing secret could not be saved. Rotate it in the Sent.dm dashboard.');

                    return self::FAILURE;
                }

                $secretSaved = true;
                $this->components->info("Signing secret saved to {$secretPath}.");
                $this->components->info('Load it into your environment and set SENT_WEBHOOK_ENABLED=true.');
            }
        } catch (APIException) {
            $this->components->error('The webhook could not be created. Check your connection and endpoint settings.');

            return self::FAILURE;
        } finally {
            fclose($secretFile);
            if (! $secretSaved) {
                unlink($secretPath);
            }
        }

        return self::SUCCESS;
    }
}
