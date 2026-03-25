<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discount_allocations')) {
            return;
        }

        Schema::create('discount_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_discount_id')->constrained('invoice_discounts')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->constrained('invoice_lines')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('entry_type', ['allocation', 'release', 'reversal']);
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->string('source_ref_type', 255)->nullable();
            $table->string('allocation_rule', 100);
            $table->timestamps();

            $table->index('invoice_discount_id');
            $table->index('invoice_line_id');
            $table->index(['invoice_discount_id', 'invoice_line_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('discount_allocations')) {
            Schema::drop('discount_allocations');
        }
    }
};
