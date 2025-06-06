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
            // Drop indexes that reference columns to be dropped
            $table->dropIndex(['semester_order', 'is_compulsory']);

            // Drop foreign key constraint first
            $table->dropForeign(['semester_id']);

            // Remove unused fields
            $table->dropColumn([
                'semester_id',
                'is_compulsory'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_units', function (Blueprint $table) {
            // Add back the removed columns
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->boolean('is_compulsory')->default(true);

            // Recreate the indexes
            $table->index(['semester_order', 'is_compulsory']);
        });
    }
};
