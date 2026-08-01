<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line-item snapshot: `merchandise_name`, `variant_label`, and
 * `gold_price_each` freeze what the student saw and paid at checkout, so a
 * later rename/reprice/archive of the Merchandise or its variant never
 * rewrites order history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemption_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('redemption_order_id')->constrained('redemption_orders')->cascadeOnDelete();
            $table->foreignId('merchandise_variant_id')->constrained('merchandise_variants');
            $table->string('merchandise_name');
            $table->string('variant_label')->nullable();
            $table->unsignedInteger('gold_price_each');
            $table->unsignedInteger('line_total');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemption_order_items');
    }
};
