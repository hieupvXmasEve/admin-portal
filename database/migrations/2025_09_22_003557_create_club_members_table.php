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
        Schema::create('club_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained('clubs')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('role', ['president', 'vice_president', 'secretary', 'treasurer', 'member'])->default('member');
            $table->enum('status', ['active', 'pending', 'rejected', 'left', 'banned'])->default('pending');
            $table->text('application_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('students')->nullOnDelete();
            $table->json('responsibilities')->nullable();
            $table->integer('participation_score')->default(0);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('club_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('role');
            $table->index(['club_id', 'student_id']);
            $table->index(['club_id', 'status']);
            $table->index(['club_id', 'role']);

            // Unique constraint to prevent duplicate active memberships
            $table->unique(['club_id', 'student_id'], 'unique_club_student_membership');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('club_members');
    }
};
