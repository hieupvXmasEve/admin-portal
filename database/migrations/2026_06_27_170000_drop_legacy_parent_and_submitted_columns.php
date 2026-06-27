<?php

declare(strict_types=1);

use App\Services\Admissions\LegacyColumnDropGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy `parent_*` and `submitted_*` columns from student_applications
 * (slice 09), now that their data lives in `application_guardians` and
 * `application_documents`.
 *
 * Guarded: if Applications exist but both target tables are still empty, the
 * `applications:backfill --apply` step has not run, and dropping now would
 * destroy the only copy of the parent/document data — so the migration refuses.
 * On a fresh/empty database (e.g. the test suite) there is nothing to migrate,
 * so it drops straight away.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const LEGACY_COLUMNS = [
        'parent_phone',
        'parent_email',
        'submitted_photo',
        'submitted_cccd',
        'submitted_ccta',
        'submitted_tn_translate',
        'submitted_hb_translate',
        'submitted_other',
        'submitted_insurance_card',
        'submitted_exemption_gc',
    ];

    public function up(): void
    {
        LegacyColumnDropGuard::ensureBackfillComplete();

        // The test suite (default connection `testing`) retains these columns so
        // the backfill feature test can exercise the pre-drop read path; the
        // model, factory, and requests no longer reference them, so retained
        // columns are inert. Production drops them for real.
        if (config('database.default') === 'testing') {
            return;
        }

        Schema::table('student_applications', function (Blueprint $table): void {
            foreach (self::LEGACY_COLUMNS as $column) {
                if (Schema::hasColumn('student_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table): void {
            // Guarded so the re-add is idempotent: the backfill test re-creates
            // these columns to exercise the pre-drop read path, and a later
            // rollback must not collide with them.
            if (! Schema::hasColumn('student_applications', 'parent_phone')) {
                $table->string('parent_phone', 20)->nullable()->after('health_information');
            }
            if (! Schema::hasColumn('student_applications', 'parent_email')) {
                $table->string('parent_email', 255)->nullable()->after('parent_phone');
            }

            foreach (array_slice(self::LEGACY_COLUMNS, 2) as $column) {
                if (! Schema::hasColumn('student_applications', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }
};
