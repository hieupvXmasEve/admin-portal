<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchandise catalog entry. `status` is a backend allow-list (see
 * App\Models\Merchandise::STATUSES) rather than a DB enum so new statuses
 * grow without a migration. `gold_price` is the CURRENT price only —
 * redemption order lines (Phase 3) snapshot the price paid separately so a
 * later price change never rewrites history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('gold_price');
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise');
    }
};
