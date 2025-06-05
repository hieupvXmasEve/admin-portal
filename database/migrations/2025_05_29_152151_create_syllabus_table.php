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
            $table->string('version', 30)->nullable();
            $table->text('description')->nullable();
            $table->integer('total_hours')->nullable();
            $table->integer('hours_per_session')->nullable();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');

            // Ensure only one active syllabus per unit per semester
            $table->unique(['unit_id', 'semester_id', 'is_active']);
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
