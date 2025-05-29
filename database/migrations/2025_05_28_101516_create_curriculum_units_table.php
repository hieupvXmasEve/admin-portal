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
        Schema::create('curriculum_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_version_id')->constrained('curriculum_versions')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->enum('group_type', ['core', 'major', 'elective', 'minor', 'second_major']);
            $table->enum('unit_scope', ['common', 'specialization_specific', 'cross_program'])->default('specialization_specific');
            $table->boolean('is_required')->default(true);
            $table->integer('year_level')->nullable(); // 1, 2, 3, 4 for bachelor
            $table->integer('semester_number')->nullable(); // 1, 2 within year
            $table->decimal('minimum_grade', 3, 2)->nullable(); // Minimum grade required (e.g., 2.0, 3.5)
            $table->text('special_conditions')->nullable(); // Special enrollment conditions
            $table->boolean('allows_concurrent_enrollment')->default(false); // Can be taken with prerequisites
            $table->timestamps();

            // Unique constraint that allows same unit in different curricula with different roles
            $table->unique(['curriculum_version_id', 'unit_id', 'group_type'], 'curriculum_unit_role_unique');

            // Add indexes for better query performance
            $table->index(['curriculum_version_id', 'group_type']);
            $table->index(['unit_scope', 'is_required']);
            $table->index(['year_level', 'semester_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_units');
    }
};
