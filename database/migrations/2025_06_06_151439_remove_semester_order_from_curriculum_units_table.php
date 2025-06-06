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
            // Drop the semester_order column
            $table->dropColumn('semester_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_units', function (Blueprint $table) {
            // Add back the semester_order column
            $table->unsignedTinyInteger('semester_order')->nullable()->comment('Suggested semester (1-12)');
        });
    }
};
