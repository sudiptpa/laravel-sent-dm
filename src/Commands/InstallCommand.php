<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'sent:install';

    protected $description = 'Publish the Sent.dm config file';

    public function handle(): int
    {
        $this->callSilent('vendor:publish', ['--tag' => 'laravel-sent-config']);

        $this->components->info('Sent.dm config published to <comment>config/sent.php</comment>.');
        $this->newLine();
        $this->components->twoColumnDetail('<comment>Next steps</comment>');
        $this->components->twoColumnDetail('Add API key to .env', 'SENT_API_KEY=your-key');
        $this->components->twoColumnDetail('Verify connectivity', 'php artisan sent:health');
        $this->newLine();
        $this->components->twoColumnDetail('<comment>Optional features</comment>');
        $this->components->twoColumnDetail('Publish migrations', 'vendor:publish --tag=laravel-sent-migrations');
        $this->components->twoColumnDetail('Enable message log', 'SENT_LOGGING_ENABLED=true');
        $this->components->twoColumnDetail('Enable opt-out tracking', 'SENT_OPT_OUT_ENABLED=true');

        return self::SUCCESS;
    }
}
