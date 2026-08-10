<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_academic_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_application_id')
                ->constrained('student_applications')
                ->cascadeOnDelete();
            $table->string('subject_code', 50);
            $table->decimal('score', 5, 2);
            $table->string('source', 20);
            $table->timestamps();

            $table->unique(['student_application_id', 'subject_code'], 'application_academic_scores_application_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_academic_scores');
    }
};
