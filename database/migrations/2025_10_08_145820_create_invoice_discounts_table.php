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
        Schema::create('invoice_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('student_invoices')->onDelete('cascade');
            $table->enum('discount_type', ['scholarship', 'voucher']);
            $table->string('discount_source', 100);
            $table->string('description', 255);
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('discount_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_discounts');
    }
};
