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
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();

            $table->string('code'); // Code term: 'SPR2025', 'FALL2025'
            $table->string('name'); // Name term: 'Spring 2025', 'Fall 2025'
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->datetime('enrollment_start_date')->nullable();
            $table->datetime('enrollment_end_date')->nullable();
            $table->boolean('is_active')->default(false); // Is the current semester?
            $table->boolean('is_archived')->default(false); // Has ended and could not edit anymore?

            $table->timestamps();
            $table->softDeletes();

            // Add index for the new fields
            $table->index(['code']);
            $table->index(['is_active', 'is_archived']);
            $table->index(['enrollment_start_date', 'enrollment_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
