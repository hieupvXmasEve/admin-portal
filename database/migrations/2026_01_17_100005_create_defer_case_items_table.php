<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defer_case_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defer_case_id')->constrained('defer_cases')->cascadeOnDelete();
            $table->foreignId('course_registration_id')->constrained('course_registrations')->cascadeOnDelete();
            $table->enum('fee_policy', ['PRESERVE', 'FORFEIT'])->nullable(); // Optional override
            $table->decimal('preserve_amount', 15, 2)->nullable(); // Amount preserved for this course
            $table->timestamps();

            // Indexes
            $table->index('defer_case_id');
            $table->index('course_registration_id');

            // Unique constraint
            $table->unique(['defer_case_id', 'course_registration_id'], 'unique_defer_course');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defer_case_items');
    }
};
