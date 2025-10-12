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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('student_invoices')->onDelete('cascade');
            $table->enum('item_type', ['tuition', 'egc', 'retake', 'miscellaneous']);
            $table->string('description', 255);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('item_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
