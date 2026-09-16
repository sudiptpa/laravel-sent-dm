<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_logs', function (Blueprint $table): void {
            $table->dropIndex(['message_id']);
            $table->unique('message_id');
        });
    }

    public function down(): void
    {
        Schema::table('sent_logs', function (Blueprint $table): void {
            $table->dropUnique(['message_id']);
            $table->index('message_id');
        });
    }
};
