<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use SentDm\Core\Exceptions\APIException;
use Sujip\SentDm\SentManager;

class TestSendCommand extends Command
{
    protected $signature = 'sent:test-send
                            {number : Recipient phone number in E.164 format}
                            {--template= : Template name to use}
                            {--connection= : Named connection to use}
                            {--sandbox : Simulate without side effects}';

    protected $description = 'Send a test message to a phone number';

    public function __construct(private readonly SentManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $number = (string) $this->argument('number');
        $template = $this->option('template');
        $template = is_string($template) ? $template : null;
        $connection = $this->option('connection');
        $connection = is_string($connection) ? $connection : null;
        $sandbox = (bool) $this->option('sandbox');

        if ($template === null) {
            $this->components->error('Please specify a template with --template=<name>');

            return self::FAILURE;
        }

        $this->components->info("Sending test message to <comment>{$number}</comment>...");

        try {
            $message = $this->manager->connection($connection)
                ->to($number)
                ->template($template);

            if ($sandbox) {
                $message = $message->sandbox();
            }

            $message->send();
            $this->components->info('<fg=green>✓ Message queued.</>');
        } catch (APIException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
