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
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');

            // Use unsignedTinyInteger to match curriculum_unit_types.id type
            $table->unsignedTinyInteger('unit_type_id');
            $table->foreign('unit_type_id')->references('id')->on('curriculum_unit_types')->onDelete('cascade');

            $table->unsignedTinyInteger('semester_order')->nullable()->comment('Suggested semester (1-12)');
            $table->boolean('is_compulsory')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate units in same curriculum version
            $table->unique(['curriculum_version_id', 'unit_id'], 'curriculum_unit_unique');

            // Add indexes for better query performance
            $table->index(['curriculum_version_id', 'unit_type_id']);
            $table->index(['semester_order', 'is_compulsory']);
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
