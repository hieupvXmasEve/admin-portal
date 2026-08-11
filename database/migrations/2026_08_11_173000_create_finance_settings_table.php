<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            // Singleton guard: unique constraint means firstOrCreate can never
            // race into two rows on concurrent first-ever access.
            $table->boolean('is_singleton')->default(true)->unique();
            $table->boolean('credit_offset_enabled')->default(false);
            $table->decimal('credit_offset_min_balance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_settings');
    }
};
