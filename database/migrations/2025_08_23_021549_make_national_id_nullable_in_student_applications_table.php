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
        Schema::table('student_applications', function (Blueprint $table) {
            // Drop the unique constraint first
            $table->dropUnique(['national_id']);
            
            // Make national_id nullable and add back the unique constraint (allowing nulls)
            $table->string('national_id', 20)->nullable()->change();
            $table->unique('national_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique(['national_id']);
            
            // Make national_id required (not nullable) and add back the unique constraint
            $table->string('national_id', 20)->nullable(false)->change();
            $table->unique('national_id');
        });
    }
};
