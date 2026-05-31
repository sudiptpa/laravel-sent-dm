<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('connection')->default('default');
            $table->string('recipient');
            $table->string('channel')->nullable();
            $table->string('template_name')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->index();
            $table->string('status')->default('queued');
            $table->string('loggable_type')->nullable();
            $table->string('loggable_id')->nullable();
            $table->timestamps();

            $table->index(['loggable_type', 'loggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_logs');
    }
};
