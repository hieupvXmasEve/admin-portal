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
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_id')->constrained('syllabus')->onDelete('cascade');
            $table->string('name', 100)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->enum('type', ['quiz', 'assignment', 'project', 'exam', 'online_activity', 'other']);
            $table->boolean('is_required_to_sit_final_exam')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_components');
    }
};
