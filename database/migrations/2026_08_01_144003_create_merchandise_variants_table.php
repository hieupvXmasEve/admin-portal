<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campus, per-color/size stock unit. `stock_quantity` is UNSIGNED — a DB
 * floor against negative inventory that backstops
 * App\Modules\Merchandise\Support\StockService's row lock and conditional
 * update.
 *
 * Note: the (merchandise_id, campus_id, color, size) unique index does not
 * dedupe rows where color/size are both NULL — MySQL/MariaDB treat NULL as
 * distinct in unique indexes, so several "no color/no size" variants per
 * merchandise+campus are technically insertable. Acceptable for Phase 2 scope
 * (a plain item without variants is expected to have exactly one such row);
 * revisit if that assumption breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchandise_id')->constrained('merchandise')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->string('sku')->nullable()->unique();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchandise_id', 'campus_id', 'color', 'size'], 'merchandise_variants_unique_combo');
            $table->index('campus_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_variants');
    }
};
