<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use SentDm\Core\Exceptions\APIException;
use Sujip\SentDm\SentManager;

class LookupCommand extends Command
{
    protected $signature = 'sent:lookup
                            {number : Phone number in E.164 format (e.g. +61412345678)}
                            {--connection= : Named connection to use}';

    protected $description = 'Look up carrier and line type for a phone number';

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $number = (string) $this->argument('number');
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;

        try {
            $response = $this->manager->connection($connection)->lookup($number);
            $data = $response->data;

            if ($data === null) {
                $this->components->error('No data returned for this number.');

                return self::FAILURE;
            }

            $valid = $data->isValid ? '<fg=green>Yes</>' : '<fg=red>No</>';

            $this->components->info("Number Lookup: <comment>{$number}</comment>");
            $this->newLine();
            $this->components->twoColumnDetail('Valid', $valid);
            $this->components->twoColumnDetail('Carrier', $data->carrierName ?? '-');
            $this->components->twoColumnDetail('Line Type', $data->lineType ?? '-');
            $this->components->twoColumnDetail('VoIP', $data->isVoip ? 'Yes' : 'No');
            $this->components->twoColumnDetail('Ported', $data->isPorted ? 'Yes' : 'No');
            $this->components->twoColumnDetail('Country Code', $data->countryCode ?? '-');
        } catch (APIException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
