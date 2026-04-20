<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egc_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedTinyInteger('block_number'); // 1 or 2
            $table->unsignedTinyInteger('level_number');
            $table->enum('result', ['pending', 'pass', 'fail'])->default('pending');
            $table->decimal('attendance_rate', 5, 2)->nullable();
            $table->boolean('is_retake')->default(false);
            $table->unsignedBigInteger('finance_charge_id')->nullable();
            $table->unsignedBigInteger('retake_discount_id')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id', 'block_number']);

            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('semester_id')->references('id')->on('semesters')->cascadeOnDelete();
            $table->foreign('finance_charge_id')->references('id')->on('finance_charges')->nullOnDelete();
            $table->foreign('retake_discount_id')->references('id')->on('invoice_discounts')->nullOnDelete();

            $table->index(['student_id', 'semester_id']);
            $table->index('finance_charge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egc_blocks');
    }
};
