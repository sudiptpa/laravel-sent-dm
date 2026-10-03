<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use SentDm\Core\Exceptions\APIException;
use Sujip\SentDm\SentManager;

class RotateWebhookSecretCommand extends Command
{
    protected $signature = 'sent:webhook:rotate-secret
                            {id : Webhook ID to rotate}
                            {--connection= : Named connection to use}
                            {--idempotency-key= : Optional Idempotency-Key header value}
                            {--secret-file= : New local environment file for the signing secret}
                            {--sandbox : Simulate without side effects}';

    protected $description = 'Rotate a Sent.dm webhook signing secret';

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;
        $idempotencyKey = $this->option('idempotency-key');
        $idempotencyKey = is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null;
        $secretPath = $this->secretPath();

        if (str_contains($secretPath, '://')) {
            $this->components->error('The secret file must be a local path.');

            return self::FAILURE;
        }

        $secretFile = $this->openSecretFile($secretPath);
        if ($secretFile === false) {
            $this->components->error('Choose a writable secret file path that does not already exist.');

            return self::FAILURE;
        }

        $secretSaved = false;
        $this->components->info("Rotating signing secret for webhook <comment>{$id}</comment>...");

        try {
            $response = $this->manager->connection($connection)->webhooks()->rotateSecret(
                $id,
                (bool) $this->option('sandbox'),
                $idempotencyKey,
            );
            $secret = $response->data?->signingSecret;

            if (! is_string($secret) || $secret === '') {
                $this->components->error('API returned no signing secret. Rotate it again from the dashboard.');

                return self::FAILURE;
            }

            $contents = 'SENT_WEBHOOK_SECRET='.json_encode($secret, JSON_THROW_ON_ERROR).PHP_EOL;

            if (@fwrite($secretFile, $contents) !== strlen($contents)) {
                $this->components->error('The signing secret could not be saved. Rotate it again from the Sent.dm dashboard.');

                return self::FAILURE;
            }

            $secretSaved = true;
            $this->components->info("Signing secret saved to {$secretPath}.");
            $this->components->info('Load it into your environment before accepting new webhook traffic.');
        } catch (APIException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } finally {
            fclose($secretFile);
            if (! $secretSaved) {
                unlink($secretPath);
            }
        }

        return self::SUCCESS;
    }

    private function secretPath(): string
    {
        $path = $this->option('secret-file');

        return is_string($path) && $path !== ''
            ? $path
            : storage_path('app/private/sent-webhook.env');
    }

    /** @return resource|false */
    private function openSecretFile(string $path): mixed
    {
        $permissions = umask(0077);
        try {
            $directory = dirname($path);
            if (! is_dir($directory) && ! @mkdir($directory, 0700, true)) {
                return false;
            }

            return @fopen($path, 'x');
        } finally {
            umask($permissions);
        }
    }
}
