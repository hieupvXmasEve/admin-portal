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
        Schema::create('ielts_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('overall_score', 3, 1);
            $table->dateTime('submitted_at');
            $table->foreignId('upload_record_id')->nullable()->constrained('upload_records')->nullOnDelete();
            $table->boolean('missing_documents')->default(false);
            $table->date('issue_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'submitted_at']);
            $table->index('overall_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ielts_certificates');
    }
};
