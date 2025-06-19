<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_group_id')->constrained('student_groups')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('added_by_lecture_id')->nullable()->constrained('lectures')->nullOnDelete();

            $table->enum('role', [
                'member',
                'leader',
                'co_leader',
                'secretary',
                'treasurer',
                'coordinator'
            ])->default('member');

            $table->enum('status', [
                'active',
                'inactive',
                'removed',
                'pending_approval'
            ])->default('active');

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->text('removal_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['student_group_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['role', 'status']);

            // Unique constraint to prevent duplicate membership
            $table->unique(['student_group_id', 'student_id'], 'unique_group_student_membership');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_members');
    }
};
