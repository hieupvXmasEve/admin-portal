<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only inventory ledger — mirrors gold_transactions' before/after
 * audit shape (see 2026_08_01_130001_convert_gold_transactions_to_integer_ledger).
 * `redemption_order_id` has no FK yet: the redemption_orders table lands in
 * Phase 3, so this stays a plain nullable indexed integer until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchandise_variant_id')->constrained('merchandise_variants')->cascadeOnDelete();
            $table->integer('change');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->string('type', 32);
            $table->unsignedBigInteger('redemption_order_id')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['merchandise_variant_id', 'created_at']);
            $table->index('redemption_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
