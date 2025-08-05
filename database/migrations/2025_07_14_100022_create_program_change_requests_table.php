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
        Schema::create('program_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('from_program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('to_program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('from_specialization_id')->nullable()->constrained('specializations')->onDelete('set null');
            $table->foreignId('to_specialization_id')->nullable()->constrained('specializations')->onDelete('set null');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->json('affected_credits')->nullable(); // Store credit mapping data
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_change_requests');
    }
};
