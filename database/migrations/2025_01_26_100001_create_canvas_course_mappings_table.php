<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canvas_course_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canvas_integration_id')->constrained('canvas_integrations')->onDelete('cascade');

            // Canvas course info
            $table->string('canvas_course_id'); // Canvas's course ID (can be string)
            $table->string('canvas_course_code')->nullable();
            $table->string('canvas_course_name')->nullable();

            // Local mapping - foreign key will be added in a later migration after course_offerings table exists
            $table->unsignedBigInteger('course_offering_id')->nullable();

            // Full Canvas course data (for reference)
            $table->json('canvas_data')->nullable();

            // Mapping status
            $table->enum('sync_status', ['pending', 'mapped', 'ignored'])->default('pending');
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['canvas_integration_id', 'sync_status']);
            $table->index('course_offering_id');
            $table->index('canvas_course_id');

            // Unique constraint
            $table->unique(['canvas_integration_id', 'canvas_course_id'], 'unique_integration_canvas_course');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_course_mappings');
    }
};
