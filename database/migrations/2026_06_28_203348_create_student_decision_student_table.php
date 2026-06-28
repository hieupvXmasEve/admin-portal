<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision <-> Student roster (ADR-0008).
     *
     * One decision covers many students; the roster is also what makes a purely
     * informational decision representable (a student attached with no transition).
     * This is the source of truth for "which students this decision covers" and is
     * deliberately distinct from the per-event authorizing decision reference.
     */
    public function up(): void
    {
        Schema::create('student_decision_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_decision_id')->constrained('student_decisions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_decision_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_decision_student');
    }
};
