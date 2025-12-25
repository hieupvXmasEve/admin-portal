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
        // Drop existing response_assignments table if it exists
        Schema::dropIfExists('response_assignments');

        Schema::create('query_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('query_ticket_id')->constrained('queries_tickets')->cascadeOnDelete();
            $table->foreignId('response_id')->nullable()->constrained('responses')->nullOnDelete();

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('form_target_id')->nullable()->constrained('form_targets')->nullOnDelete();

            $table->foreignId('from_assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_assignee_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['assign', 'reassign', 'unassign']);

            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['query_ticket_id', 'created_at'], 'idx_ticket_time');
            $table->index('to_assignee_user_id', 'idx_to_user');
            $table->index('assigned_by_user_id', 'idx_by_user');
            $table->index('department_id', 'idx_dept');
            $table->index('form_target_id', 'idx_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_assignments');
        
        // Optionally recreate response_assignments if needed for rollback
        Schema::create('response_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('responses')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['assign', 'reassign', 'unassign']);
            $table->timestamps();
        });
    }
};
