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
        Schema::table('students', function (Blueprint $table) {
            // Rename old intake_gc to migrate data later
            $table->renameColumn('intake_gc', 'intake_gc_old');
            
            // Add intake_major
            $table->unsignedBigInteger('intake_major')->nullable()->after('intake_course');
        });

        Schema::table('students', function (Blueprint $table) {
            // Add new intake_gc as FK
            $table->unsignedBigInteger('intake_gc')->nullable()->after('intake_mode');
            
            $table->foreign('intake_gc')->references('id')->on('semesters');
            $table->foreign('intake_major')->references('id')->on('semesters');
        });

        // Migrate data for intake_gc: map string codes to semester IDs
        $semesters = DB::table('semesters')->pluck('id', 'code');
        foreach ($semesters as $code => $id) {
            DB::table('students')->where('intake_gc_old', (string)$code)->update(['intake_gc' => $id]);
            DB::table('students')->where('intake_course', (string)$code)->update(['intake_major' => $id]);
        }

        // Handle numeric-looking strings just in case for intake_gc
        DB::table('students')
            ->whereNotNull('intake_gc_old')
            ->whereNull('intake_gc')
            ->whereRaw('intake_gc_old REGEXP "^[0-9]+$"')
            ->update(['intake_gc' => DB::raw('CAST(intake_gc_old AS UNSIGNED)')]);

        // Handle numeric-looking strings just in case for intake_major
        DB::table('students')
            ->whereNotNull('intake_course')
            ->whereNull('intake_major')
            ->whereRaw('intake_course REGEXP "^[0-9]+$"')
            ->update(['intake_major' => DB::raw('CAST(intake_course AS UNSIGNED)')]);

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('intake_gc_old');
            
            // Update status enum to include intake_major
            DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('active','inactive','suspended','graduated','intake_pre_uni_gc','intake_course','intake_major','deferred','dropout','dropout_transfer','pending','admission_deferred') DEFAULT 'pending'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Revert enum
            DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('active','inactive','suspended','graduated','intake_pre_uni_gc','intake_course','deferred','dropout','dropout_transfer','pending','admission_deferred') DEFAULT 'pending'");

            $table->dropForeign(['intake_major']);
            $table->dropForeign(['intake_gc']);
            $table->dropColumn(['intake_major', 'intake_gc']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('intake_gc')->nullable()->after('intake_mode');
        });
    }
};
