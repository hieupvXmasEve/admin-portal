<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add columns to responses table
        Schema::table('responses', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('query_status')->nullable()->after('status'); 
        });

        // 2. Modify target_scope_type enum in responses table
        DB::statement("ALTER TABLE responses MODIFY COLUMN target_scope_type ENUM('section', 'class_session', 'course', 'global', 'department', 'semester') NOT NULL");

        // 3. Create response_assignments table (Audit for Query workflow)
        Schema::create('response_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('responses')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['assign', 'reassign', 'unassign']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('response_assignments');

        // Revert target_scope_type enum
        DB::statement("ALTER TABLE responses MODIFY COLUMN target_scope_type ENUM('section', 'class_session', 'course', 'global') NOT NULL");

        Schema::table('responses', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropColumn(['assigned_to_user_id', 'query_status']);
        });
    }
};
