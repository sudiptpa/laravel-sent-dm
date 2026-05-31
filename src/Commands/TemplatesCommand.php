<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use SentDm\Core\Exceptions\APIException;
use Sujip\SentDm\SentManager;

class TemplatesCommand extends Command
{
    protected $signature = 'sent:templates
                            {--connection= : Named connection to use}
                            {--page=1 : Page number}
                            {--per-page=50 : Results per page}';

    protected $description = 'List templates from the Sent.dm account';

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;
        $page = max(1, (int) $this->option('page'));
        $perPage = max(1, (int) $this->option('per-page'));

        try {
            $response = $this->manager->connection($connection)->listTemplates($page, $perPage);
            $data = $response->data;

            if ($data === null || empty($data->templates)) {
                $this->components->info('No templates found.');

                return self::SUCCESS;
            }

            $rows = [];
            foreach ($data->templates as $template) {
                $rows[] = [
                    $template->id ?? '-',
                    $template->name ?? '-',
                    $template->category ?? '-',
                    $template->status ?? '-',
                    implode(', ', $template->channels ?? []),
                ];
            }

            $this->table(['ID', 'Name', 'Category', 'Status', 'Channels'], $rows);
        } catch (APIException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
