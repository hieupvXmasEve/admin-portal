<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');

            // Polymorphic fields for booking entity (can be user or lecture)
            $table->string('booked_by_type'); // 'user' or 'lecture'
            $table->unsignedBigInteger('booked_by_id');

            // Polymorphic fields for approval entity (can be user or lecture)
            $table->string('approved_by_type')->nullable(); // 'user' or 'lecture'
            $table->unsignedBigInteger('approved_by_id')->nullable();

            $table->string('title', 200); // Booking title/purpose
            $table->text('description')->nullable();

            // Booking time details
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            // $table->integer('expected_attendees')->nullable();

            $table->enum('booking_type', [
                'class',
                'exam',
                'meeting',
                'event',
                'maintenance',
                'personal_study',
                'workshop',
                'other',
            ])->default('meeting');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'cancelled',
                'completed',
            ])->default('pending');

            $table->enum('priority', [
                'low',
                'normal',
                'high',
                'urgent',
            ])->default('normal');

            // Recurring booking support
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurrence_type', [
                'daily',
                'weekly',
                'biweekly',
                'monthly',
            ])->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->json('recurrence_days')->nullable(); // ["Monday", "Wednesday", "Friday"]
            $table->foreignId('parent_booking_id')->nullable()->constrained('room_bookings')->nullOnDelete();

            // Equipment and setup requirements
            $table->json('required_equipment')->nullable(); // ["projector", "microphone"]
            $table->json('setup_requirements')->nullable(); // ["theater_style", "u_shape", "conference"]
            $table->text('special_requirements')->nullable();

            // Contact and notification details
            $table->string('contact_person', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->boolean('send_reminders')->default(true);

            // Administrative fields
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance and conflict detection
            $table->index(['room_id', 'booking_date', 'start_time', 'end_time'], 'room_time_conflict_idx');
            $table->index(['booked_by_type', 'booked_by_id'], 'room_bookings_booked_by_idx');
            $table->index(['approved_by_type', 'approved_by_id'], 'room_bookings_approved_by_idx');
            $table->index(['status', 'booking_date']);
            $table->index(['booking_type', 'status']);
            $table->index(['is_recurring', 'parent_booking_id']);
            $table->index(['priority', 'status']);

            // Prevent double booking (overlapping times for same room)
            // Note: This will be enforced at application level due to time overlap complexity
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_bookings');
    }
};
