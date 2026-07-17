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
        Schema::table('parent_student', function (Blueprint $table) {
            // NULL values do not collide in a unique index. The generated key
            // therefore permits many non-primary guardians, while rejecting a
            // second primary guardian for the same student.
            $table->unsignedBigInteger('primary_parent_key')
                ->nullable()
                ->storedAs('CASE WHEN is_primary = 1 THEN student_id ELSE NULL END')
                ->after('is_primary');
            $table->unique('primary_parent_key', 'parent_student_one_primary_unique');
            $table->unique('parent_id', 'parent_student_one_student_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_student', function (Blueprint $table) {
            $table->dropUnique('parent_student_one_primary_unique');
            $table->dropUnique('parent_student_one_student_unique');
            $table->dropColumn('primary_parent_key');
        });
    }
};
