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
        Schema::create('event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->enum('status', ['registered', 'checked_in', 'completed', 'cancelled'])->default('registered');
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamp('checkin_time')->nullable();
            $table->json('checkin_device_info')->nullable();
            $table->foreignId('checkin_staff_id')->nullable()->constrained('users');
            $table->boolean('gold_awarded')->default(false);
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate registrations
            $table->unique(['event_id', 'student_id']);

            // Indexes for performance
            $table->index(['student_id', 'status']);
            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_participants');
    }
};
