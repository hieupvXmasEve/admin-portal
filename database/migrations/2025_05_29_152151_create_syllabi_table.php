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
        Schema::create('syllabi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            $table->string('version', 10)->nullable();
            $table->text('description')->nullable();
            $table->integer('total_hours')->nullable();
            $table->integer('hours_per_session')->nullable();
            $table->foreignId('effective_from_semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Ensure only one active syllabus per unit per semester
            $table->unique(['unit_id', 'effective_from_semester_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
