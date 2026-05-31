<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_warning_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->decimal('attendance_warning_ratio', 4, 2)->default(0.50);
            $table->json('channels')->nullable();
            $table->string('academic_warning_title')->default('Academic standing warning');
            $table->text('academic_warning_body');
            $table->string('attendance_warning_title')->default('Attendance warning');
            $table->text('attendance_warning_body');
            $table->string('attendance_exceeded_title')->default('Attendance limit exceeded');
            $table->text('attendance_exceeded_body');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('campus_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_warning_settings');
    }
};
