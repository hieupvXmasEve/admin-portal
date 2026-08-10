<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * No FK exists on `campus_code` today — see phase-01-schema-and-storage.md §2.
 * Rollback is safe only before the first CRM sync; once a synced row holds
 * NULL, `down()` cannot restore NOT NULL without corrupting data, so it aborts
 * instead of coercing NULL to ''.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('campus_code', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        $nullCount = DB::table('student_applications')->whereNull('campus_code')->count();

        if ($nullCount > 0) {
            throw new RuntimeException(
                "Cannot restore NOT NULL on student_applications.campus_code: {$nullCount} row(s) hold NULL. ".
                'Forward-fix only once a CRM sync has run — see phase-01-schema-and-storage.md.'
            );
        }

        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('campus_code', 20)->nullable(false)->change();
        });
    }
};
