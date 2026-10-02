<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use SentDm\Core\Exceptions\APIException;
use Sujip\SentDm\SentManager;

class HealthCommand extends Command
{
    protected $signature = 'sent:health
                            {--connection= : Named connection to check (defaults to the default connection)}';

    protected $description = 'Check API connectivity and account status';

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;

        $label = $connection ?? $this->manager->getDefaultDriver();

        $this->components->info("Sent.dm Health Check, connection: <comment>{$label}</comment>");
        $this->newLine();
        $this->reportLocalConfiguration($label);
        $this->newLine();

        try {
            $response = $this->manager->connection($connection)->account();
            $data = $response->data;

            if ($data === null) {
                $this->components->error('API returned empty response.');

                return self::FAILURE;
            }

            $this->components->twoColumnDetail('Status', '<fg=green>✓ Connected</>');
            $this->components->twoColumnDetail('Type', (string) $data->type);
            $this->components->twoColumnDetail('Name', (string) $data->name);
            $this->components->twoColumnDetail('Email', (string) $data->email);

            if ($data->channels !== null) {
                $this->newLine();
                $this->components->twoColumnDetail('<comment>Channels</comment>');

                foreach (['sms', 'whatsapp', 'rcs'] as $channel) {
                    $ch = match ($channel) {
                        'sms' => $data->channels->sms,
                        'whatsapp' => $data->channels->whatsapp,
                        default => $data->channels->rcs,
                    };

                    if ($ch !== null) {
                        $status = $ch->configured ? '<fg=green>✓ Configured</>' : '<fg=red>✗ Not configured</>';
                        $detail = $ch->configured && isset($ch->phoneNumber) ? " ({$ch->phoneNumber})" : '';
                        $this->components->twoColumnDetail(strtoupper($channel), $status.$detail);
                    }
                }
            }
        } catch (APIException $e) {
            $this->components->twoColumnDetail('Status', '<fg=red>✗ Failed</>');
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reportLocalConfiguration(string $connection): void
    {
        $this->components->twoColumnDetail('<comment>Configuration</comment>');

        $apiKey = config("sent.connections.{$connection}.api_key");
        $this->components->twoColumnDetail('API key', $this->configured($apiKey));

        $queueConnection = config('sent.queue.connection');
        $queueName = config('sent.queue.name', 'default');
        $queueTries = config('sent.queue.tries', 3);
        $queueBackoff = config('sent.queue.backoff', [1, 5, 10]);

        $this->components->twoColumnDetail('Queue connection', $this->displayValue($queueConnection, 'default'));
        $this->components->twoColumnDetail('Queue name', $this->displayValue($queueName, 'default'));
        $this->components->twoColumnDetail('Queue tries', is_numeric($queueTries) ? (string) max(1, (int) $queueTries) : '<fg=yellow>invalid, using 3</>');
        $this->components->twoColumnDetail('Queue backoff', $this->displayBackoff($queueBackoff));

        $this->newLine();
        $this->components->twoColumnDetail('<comment>Webhook</comment>');
        $this->components->twoColumnDetail('Enabled', $this->enabled(config('sent.webhook.enabled')));
        $this->components->twoColumnDetail('Path', $this->displayValue(config('sent.webhook.path'), 'sent/webhook'));
        $this->components->twoColumnDetail('Secret', $this->configured(config('sent.webhook.secret')));
        $this->components->twoColumnDetail('Dedup TTL', $this->numericConfig(config('sent.webhook.dedup_ttl'), 86400));

        $this->newLine();
        $this->components->twoColumnDetail('<comment>Database features</comment>');
        $this->components->twoColumnDetail('Logging', $this->enabled(config('sent.logging.enabled')).' '.$this->tableStatus('sent_logs', (bool) config('sent.logging.enabled')));
        $this->components->twoColumnDetail('Opt-out', $this->enabled(config('sent.opt_out.enabled')).' '.$this->tableStatus('sent_opt_outs', (bool) config('sent.opt_out.enabled')));
        $this->components->twoColumnDetail('Opt-out guard', $this->enabled(config('sent.opt_out.guard')));
    }

    private function configured(mixed $value): string
    {
        return is_string($value) && $value !== '' ? '<fg=green>configured</>' : '<fg=yellow>missing</>';
    }

    private function enabled(mixed $value): string
    {
        return (bool) $value ? '<fg=green>enabled</>' : '<fg=gray>disabled</>';
    }

    private function displayValue(mixed $value, string $fallback): string
    {
        return is_string($value) && $value !== '' ? $value : "<fg=gray>{$fallback}</>";
    }

    private function numericConfig(mixed $value, int $fallback): string
    {
        return is_numeric($value) ? (string) max(1, (int) $value) : "<fg=yellow>invalid, using {$fallback}</>";
    }

    private function displayBackoff(mixed $value): string
    {
        if (! is_array($value)) {
            return '<fg=yellow>invalid, using 1, 5, 10</>';
        }

        $backoff = array_values(array_filter(
            array_map(fn (mixed $item): ?int => is_numeric($item) ? max(0, (int) $item) : null, $value),
            fn (?int $item): bool => $item !== null,
        ));

        return $backoff === [] ? '<fg=yellow>invalid, using 1, 5, 10</>' : implode(', ', $backoff);
    }

    private function tableStatus(string $table, bool $needed): string
    {
        if (! $needed) {
            return '';
        }

        try {
            return Schema::hasTable($table) ? '<fg=green>table ready</>' : "<fg=yellow>{$table} table missing</>";
        } catch (\Throwable) {
            return '<fg=yellow>table check unavailable</>';
        }
    }
}
