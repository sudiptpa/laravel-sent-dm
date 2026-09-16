<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Installs running pre-2.0 code could have logged two rows for the same
     * message_id (SyncMessageStatus used to reconcile on a plain index, not a
     * unique one). The next migration adds a unique index on message_id, so
     * any leftover duplicate has to go first or that migration fails outright.
     */
    public function up(): void
    {
        $duplicateIds = DB::table('sent_logs')
            ->select('message_id')
            ->whereNotNull('message_id')
            ->groupBy('message_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('message_id');

        foreach ($duplicateIds as $messageId) {
            $keepId = DB::table('sent_logs')
                ->where('message_id', $messageId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('sent_logs')
                ->where('message_id', $messageId)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    public function down(): void
    {
        // Deleted rows can't be restored; nothing to reverse.
    }
};
