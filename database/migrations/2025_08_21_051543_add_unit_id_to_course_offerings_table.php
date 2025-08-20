<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('curriculum_unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
            $table->index('unit_id');
        });

        // Populate unit_id from existing curriculum_units
        DB::statement('
            UPDATE course_offerings co
            JOIN curriculum_units cu ON co.curriculum_unit_id = cu.id
            SET co.unit_id = cu.unit_id
        ');

        // Make unit_id required after population
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
