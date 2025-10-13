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
        \DB::statement("ALTER TABLE assessment_components MODIFY COLUMN type ENUM('quiz', 'assignment', 'project', 'exam', 'online_activity', 'attendance', 'other') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::statement("ALTER TABLE assessment_components MODIFY COLUMN type ENUM('quiz', 'assignment', 'project', 'exam', 'online_activity', 'other') NOT NULL");
    }
};
