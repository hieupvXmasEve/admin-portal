<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egc_retake_discount_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_discount_id');
            $table->unsignedBigInteger('source_egc_block_id');
            $table->unsignedBigInteger('target_finance_charge_id');
            $table->timestamps();

            $table->unique('target_finance_charge_id'); // prevents double discount on same charge

            $table->foreign('invoice_discount_id')->references('id')->on('invoice_discounts')->cascadeOnDelete();
            $table->foreign('source_egc_block_id')->references('id')->on('egc_blocks')->cascadeOnDelete();
            $table->foreign('target_finance_charge_id')->references('id')->on('finance_charges')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egc_retake_discount_links');
    }
};
