<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->foreignId('billing_cycle_id')->nullable()->constrained('billing_cycles')->nullOnDelete();

            $table->enum('charge_type', [
                'tuition_term',
                'egc_level_fee',
                'retake_fee',
                'course_fee',
                'manual_fee',
                'defer_credit',
                'egc_exempt_credit',
                'scholarship_credit',
                'voucher_credit',
                'adjustment',
                'admission_fee'
            ]);
            $table->decimal('amount', 15, 2); // Positive = charge, Negative = credit
            $table->string('description', 255);
            $table->dateTime('effective_at');
            $table->enum('status', ['active', 'void'])->default('active');

            // Polymorphic source for traceability
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            // Audit fields
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('student_id');
            $table->index('semester_id');
            $table->index('billing_cycle_id');
            $table->index('charge_type');
            $table->index('status');
            $table->index(['source_type', 'source_id']);
            $table->index(['student_id', 'semester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_charges');
    }
};
