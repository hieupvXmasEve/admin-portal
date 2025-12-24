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
        Schema::dropIfExists('student_form_assignments');
        Schema::create('student_form_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('form_target_id')->constrained('form_targets')->onDelete('cascade');
            $table->string('status')->default('not_started')->index(); // not_started, completed
            $table->foreignId('response_id')->nullable()->constrained('responses')->onDelete('set null');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate assignments for same target
            $table->unique(['student_id', 'form_target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_form_assignments');
    }
};
