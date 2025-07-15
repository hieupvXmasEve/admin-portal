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
        Schema::create('syllabus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_unit_id')
                ->constrained('curriculum_units')
                ->onDelete('cascade');
            $table->string('version', 30)->nullable();
            $table->text('description')->nullable();
            $table->integer('total_hours')->nullable();
            $table->integer('hours_per_session')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Đảm bảo mỗi curriculum_unit chỉ có 1 syllabus active
            $table->unique(['curriculum_unit_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabus');
    }
};
