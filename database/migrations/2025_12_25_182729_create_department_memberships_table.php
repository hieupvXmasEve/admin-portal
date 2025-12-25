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
        Schema::create('department_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('department_role', ['head', 'staff'])->default('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'user_id'], 'uq_dept_user');
            $table->index(['department_id', 'department_role'], 'idx_dept_role');
            $table->index('user_id', 'idx_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_memberships');
    }
};
