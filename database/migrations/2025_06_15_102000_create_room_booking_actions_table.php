<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_booking_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_booking_id')->constrained('room_bookings')->onDelete('cascade');

            $table->enum('action_type', [
                'created',
                'updated',
                'approved',
                'rejected',
                'cancelled',
                'completed',
            ]);

            // Polymorphic fields for action performer
            $table->string('action_by_type'); // 'user', 'student', 'lecturer'
            $table->unsignedBigInteger('action_by_id');

            $table->text('note')->nullable();

            // Store old and new values for tracking changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['room_booking_id', 'action_type']);
            $table->index(['action_by_type', 'action_by_id'], 'room_booking_actions_action_by_idx');
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_booking_actions');
    }
};

