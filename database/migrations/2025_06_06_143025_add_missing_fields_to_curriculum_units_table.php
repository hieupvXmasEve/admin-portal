<?php

declare(strict_types=1);

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
            $table->unsignedTinyInteger('year_level')->nullable()->comment('Academic year level (1-3)');
            // $table->unsignedTinyInteger('semester_number')->nullable()->comment('Semester within the year (1-9)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_units', function (Blueprint $table) {
            $table->dropColumn(['year_level', 'semester_number']);
        });
    }
};
