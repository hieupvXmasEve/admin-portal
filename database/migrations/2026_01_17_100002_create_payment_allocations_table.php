<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained('finance_charges')->cascadeOnDelete();
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('payment_id');
            $table->index('charge_id');
            $table->index(['payment_id', 'charge_id']);

            // Unique constraint to prevent duplicate allocations
            $table->unique(['payment_id', 'charge_id'], 'unique_payment_charge_allocation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
