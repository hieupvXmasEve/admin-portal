<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM legitimately sends blank/duplicated emails; identity is `student_code`
 * (D1), not email. Manual-entry FormRequests keep their own `unique` validation
 * rule — only the CRM path may create duplicate rows once the DB index is gone.
 * Rollback is safe only before duplicates/blanks exist; otherwise it aborts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropUnique('student_applications_email_unique');
        });

        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        $blankCount = DB::table('student_applications')->whereNull('email')->orWhere('email', '')->count();
        $duplicateCount = DB::table('student_applications')
            ->select('email')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($blankCount > 0 || $duplicateCount > 0) {
            throw new RuntimeException(
                "Cannot restore NOT NULL UNIQUE on student_applications.email: {$blankCount} blank row(s), ".
                "{$duplicateCount} duplicate email group(s). Forward-fix only once a CRM sync has run — ".
                'see phase-01-schema-and-storage.md.'
            );
        }

        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('email', 255)->nullable(false)->change();
        });

        Schema::table('student_applications', function (Blueprint $table) {
            $table->unique('email', 'student_applications_email_unique');
        });
    }
};
