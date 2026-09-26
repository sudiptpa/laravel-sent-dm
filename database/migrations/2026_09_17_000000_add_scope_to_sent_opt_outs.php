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
            $table->string('scope', 191)->default('');
            $table->dropUnique(['phone_number']);
            $table->unique(['phone_number', 'scope']);
        });
    }

    public function down(): void
    {
        if (DB::table('sent_opt_outs')->where('scope', '!=', '')->exists()) {
            throw new RuntimeException('Resolve scoped consent records before rolling back this migration.');
        }

        Schema::table('sent_opt_outs', function (Blueprint $table): void {
            $table->dropUnique(['phone_number', 'scope']);
            $table->dropColumn('scope');
            $table->unique('phone_number');
        });
    }
};
