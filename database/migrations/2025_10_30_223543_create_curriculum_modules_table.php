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
        Schema::create('curriculum_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_version_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('year_level')->nullable();
            $table->unsignedTinyInteger('semester_number')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('group_name')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_version_id', 'module_id']);
            $table->index(['curriculum_version_id', 'year_level', 'semester_number'], 'cm_cv_year_sem_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_modules');
    }
};
