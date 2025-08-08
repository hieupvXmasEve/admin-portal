<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->enum('hold_type', [
                'financial',
                'academic',
                'disciplinary',
                'administrative',
                'health',
                'library',
            ]);
            $table->enum('hold_category', [
                'registration',
                'graduation',
                'transcript',
                'all',
            ])->default('registration');

            // Hold Details
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->nullable(); // For financial holds
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');

            // Status
            $table->enum('status', ['active', 'resolved', 'waived', 'expired'])->default('active');
            $table->date('placed_date');
            $table->date('due_date')->nullable();
            $table->date('resolved_date')->nullable();

            // Management
            $table->foreignId('placed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();

            // System
            $table->timestamps();

            // Indexes
            $table->index('student_id');
            $table->index(['hold_type', 'status']);
            $table->index('hold_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_holds');
    }
};
