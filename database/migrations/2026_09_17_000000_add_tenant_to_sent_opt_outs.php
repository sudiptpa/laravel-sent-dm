<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sent_opt_outs', function (Blueprint $table): void {
            $table->string('tenant', 191)->default('');
            $table->dropUnique(['phone_number']);
            $table->unique(['phone_number', 'tenant']);
        });
    }

    public function down(): void
    {
        if (DB::table('sent_opt_outs')->where('tenant', '!=', '')->exists()) {
            throw new RuntimeException('Resolve tenant-aware opt-out records before rolling back this migration.');
        }

        Schema::table('sent_opt_outs', function (Blueprint $table): void {
            $table->dropUnique(['phone_number', 'tenant']);
            $table->dropColumn('tenant');
            $table->unique('phone_number');
        });
    }
};
