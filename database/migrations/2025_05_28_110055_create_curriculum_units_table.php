<?php

declare(strict_types=1);

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
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('cascade');

            $table->enum('type', ['core', 'major', 'elective'])->nullable();
            $table->enum('unit_scope', ['program', 'common', 'specialization_specific', 'cross_program'])->nullable();
            $table->unsignedTinyInteger('year_level')->nullable()->comment('Academic year level (1-3)');
            $table->unsignedTinyInteger('semester_number')->nullable()->comment('Suggested semester number within the course (1-9)');
            $table->boolean('is_required')->default(true);
            $table->decimal('minimum_grade', 4, 2)->nullable()->comment('Minimum grade required for completion (optional)');
            $table->text('note')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate units in the same curriculum
            $table->unique(['curriculum_version_id', 'unit_id'], 'curriculum_unit_unique');

            // Indexes for query performance
            $table->index(['curriculum_version_id', 'type']);
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
