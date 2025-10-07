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
        Schema::create('tuition_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_version_id')->constrained('curriculum_versions')->onDelete('cascade');
            $table->foreignId('intake_semester_id')->constrained('semesters')->onDelete('cascade');
            $table->decimal('total_amount', 15, 2);
            $table->string('currency', 3)->default('VND');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['curriculum_version_id', 'intake_semester_id'], 'unique_curriculum_intake');
            $table->index('curriculum_version_id', 'idx_curriculum');
            $table->index('intake_semester_id', 'idx_intake');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tuition_plans');
    }
};
