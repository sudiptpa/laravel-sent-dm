<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_opt_outs', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_number')->unique();
            $table->boolean('opted_out')->default(true);
            $table->string('reason')->nullable();
            $table->timestamp('last_opted_out_at')->nullable();
            $table->timestamp('last_opted_in_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_opt_outs');
    }
};
