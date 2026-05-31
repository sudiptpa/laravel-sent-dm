<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
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

        $this->components->info("Sent.dm Health Check — connection: <comment>{$label}</comment>");
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
}
