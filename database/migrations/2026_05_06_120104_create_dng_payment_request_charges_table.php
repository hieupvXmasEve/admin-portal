<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('dng_payment_request_charges');
        Schema::create('dng_payment_request_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dng_payment_request_id')->constrained('dng_payment_requests')->cascadeOnDelete();
            $table->foreignId('finance_charge_id')->constrained('finance_charges')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['dng_payment_request_id', 'finance_charge_id'], 'dng_req_charge_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dng_payment_request_charges');
    }
};
