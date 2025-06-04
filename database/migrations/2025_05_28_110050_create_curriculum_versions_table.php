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
        Schema::create('curriculum_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs');
            $table->foreignId('specialization_id')->nullable()->constrained('specializations')->onDelete('cascade');
            $table->string('version_code', 20)->nullable();
            $table->foreignId('effective_from_semester_id')->nullable()->constrained('semesters');
            $table->enum('scope', ['program', 'specialization'])->default('specialization');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'specialization_id', 'scope']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_versions');
    }
};
