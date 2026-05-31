<?php

declare(strict_types=1);

namespace Sujip\SentDm\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sujip\SentDm\Enums\SentLogStatus;

class StatsCommand extends Command
{
    protected $signature = 'sent:stats
                            {--table=sent_logs : The message log table name}';

    protected $description = 'Show aggregate message statistics from the local sent_logs table';

    public function handle(): int
    {
        $table = $this->option('table');
        $table = is_string($table) ? $table : 'sent_logs';

        try {
            $rows = DB::table($table)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get();
        } catch (\Throwable) {
            $this->components->error("Could not query table [{$table}]. Have you run the Sent.dm migrations?");

            return self::FAILURE;
        }

        if ($rows->isEmpty()) {
            $this->components->info('No messages logged yet.');

            return self::SUCCESS;
        }

        $statuses = array_column(SentLogStatus::cases(), 'value');
        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($rows as $row) {
            $rawStatus = $row->status;
            $rawTotal = $row->total;
            $status = is_string($rawStatus) ? $rawStatus : '';
            $total = is_numeric($rawTotal) ? (int) $rawTotal : 0;
            $counts[$status] = $total;
        }

        $tableRows = [];
        $grand = 0;
        foreach ($statuses as $status) {
            $count = $counts[$status] ?? 0;
            $grand += $count;
            $tableRows[] = [ucfirst($status), number_format($count)];
        }

        foreach ($counts as $status => $count) {
            if (! in_array($status, $statuses, true)) {
                $grand += $count;
                $tableRows[] = [ucfirst($status), number_format($count)];
            }
        }

        $tableRows[] = ['─────────', '─────────'];
        $tableRows[] = ['Total', number_format($grand)];

        $this->table(['Status', 'Count'], $tableRows);

        return self::SUCCESS;
    }
}
