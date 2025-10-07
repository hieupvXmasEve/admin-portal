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
        Schema::create('voucher_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('voucher_type', ['informational', 'discount']);
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->nullable();
            $table->decimal('discount_value', 15, 2)->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('voucher_type');
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_definitions');
    }
};
