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
        Schema::create('student_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('field_name'); // 'status', 'gc_current_level', etc.
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->text('reason');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['student_id', 'changed_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_changes');
    }
};
