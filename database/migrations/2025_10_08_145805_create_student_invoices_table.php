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
        Schema::create('student_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('billing_cycle_id')->constrained('billing_cycles')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'pending', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('billing_cycle_id');
            $table->index('status');
            $table->index('invoice_number');
            
            // Unique constraint to prevent duplicate invoices for same student and billing cycle
            $table->unique(['student_id', 'billing_cycle_id'], 'unique_student_billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_invoices');
    }
};
