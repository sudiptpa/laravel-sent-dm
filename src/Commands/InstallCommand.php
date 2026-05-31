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

        return self::SUCCESS;
    }
}
