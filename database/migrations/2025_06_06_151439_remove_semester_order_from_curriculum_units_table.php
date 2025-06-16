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
        Schema::table('curriculum_units', function (Blueprint $table) {
            // Drop the semester_number column
            $table->dropColumn('semester_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_units', function (Blueprint $table) {
            // Add back the semester_number column
            $table->unsignedTinyInteger('semester_number')->nullable()->comment('Suggested semester (1-12)');
        });
    }
};
