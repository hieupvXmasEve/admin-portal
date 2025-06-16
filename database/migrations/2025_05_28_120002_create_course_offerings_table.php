<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->foreignId('instructor_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('section_code', 10)->nullable(); // e.g., "A", "B", "01", "02"
            $table->integer('max_capacity')->default(30);
            $table->integer('current_enrollment')->default(0);
            $table->integer('waitlist_capacity')->default(10);
            $table->integer('current_waitlist')->default(0);

            $table->enum('delivery_mode', [
                'in_person',
                'online',
                'hybrid',
                'blended'
            ])->default('in_person');

            $table->json('schedule_days')->nullable(); // ["Monday", "Wednesday", "Friday"]
            $table->time('schedule_time_start')->nullable();
            $table->time('schedule_time_end')->nullable();
            $table->string('location')->nullable();

            $table->boolean('is_active')->default(true);
            $table->enum('enrollment_status', [
                'open',
                'closed',
                'waitlist_only',
                'cancelled'
            ])->default('open');

            // New registration date fields
            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();

            $table->text('special_requirements')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['semester_id', 'unit_id']);
            $table->index(['instructor_id', 'semester_id']);
            $table->index(['is_active', 'enrollment_status']);
            $table->index(['delivery_mode']);
            $table->index(['current_enrollment', 'max_capacity']);
            $table->index(['registration_start_date', 'registration_end_date'], 'co_registration_dates_idx');

            // Unique constraint for section codes within semester-unit combination
            $table->unique(['semester_id', 'unit_id', 'section_code'], 'unique_semester_unit_section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
