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
        Schema::create('tuition_plan_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tuition_plan_id')->constrained('tuition_plans')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->integer('term_number');
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->unique(['tuition_plan_id', 'term_number'], 'unique_plan_term');
            $table->index('tuition_plan_id', 'idx_tuition_plan');
            $table->index('semester_id', 'idx_semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuition_plan_terms');
    }
};
