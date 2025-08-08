<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();

            $table->string('name', 100); // e.g., "A101", "Computer Lab 1", "Main Auditorium"
            $table->string('code', 20)->unique(); // e.g., "A101", "CL1", "AUD1"
            $table->string('building', 50)->nullable(); // Building name or code
            $table->string('floor', 10)->nullable(); // Floor number/name

            $table->enum('type', [
                'classroom',
                'laboratory',
                'computer_lab',
                'auditorium',
                'meeting_room',
                'library',
                'study_room',
                'workshop',
                'office',
                'other',
            ])->default('classroom');

            $table->integer('capacity')->default(1); // Maximum occupancy
            // $table->integer('exam_capacity')->nullable(); // Capacity during exams (usually lower)

            // Equipment and facilities (JSON for flexibility)
            // $table->json('equipment')->nullable(); // ["projector", "computer", "whiteboard", "sound_system"]
            // $table->json('software')->nullable(); // ["microsoft_office", "autocad", "matlab"] for labs
            // $table->json('accessibility_features')->nullable(); // ["wheelchair_accessible", "hearing_loop"]

            $table->enum('status', [
                'available',
                'occupied',
                'maintenance',
                'out_of_service',
                'reserved',
            ])->default('available');

            $table->boolean('is_bookable')->default(true); // Can this room be booked?
            $table->boolean('requires_approval')->default(false); // Requires admin approval for booking

            // Scheduling constraints
            $table->time('available_from')->default('07:00:00'); // Daily availability start
            $table->time('available_until')->default('18:00:00'); // Daily availability end
            $table->json('blocked_days')->nullable(); // ["Saturday", "Sunday"] or specific dates

            $table->text('description')->nullable();
            $table->text('usage_guidelines')->nullable();
            $table->text('booking_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['campus_id', 'type']);
            $table->index(['status', 'is_bookable']);
            $table->index(['capacity']);
            $table->index(['building', 'floor']);
            $table->index(['available_from', 'available_until']);

            // Unique constraint for room codes
            $table->unique(['campus_id', 'code'], 'unique_campus_room_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
