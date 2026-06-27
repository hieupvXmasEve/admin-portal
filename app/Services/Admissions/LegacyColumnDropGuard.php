<?php

declare(strict_types=1);

namespace App\Services\Admissions;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Safety gate for the migration that drops the legacy `parent_*` / `submitted_*`
 * columns (slice 09).
 *
 * The legacy columns hold the only copy of the parent/document data until the
 * `applications:backfill` command moves it into `application_guardians` /
 * `application_documents`. This guard refuses the drop while Applications exist
 * but both target tables are still empty — a strong signal the backfill has not
 * run — so the data is never destroyed without a home. A fresh/empty database
 * has nothing to migrate and passes straight through.
 */
class LegacyColumnDropGuard
{
    public static function ensureBackfillComplete(): void
    {
        $applications = DB::table('student_applications')->count();

        if ($applications === 0) {
            return;
        }

        $guardians = DB::table('application_guardians')->count();
        $documents = DB::table('application_documents')->count();

        if ($guardians === 0 && $documents === 0) {
            throw new RuntimeException(
                'Refusing to drop legacy parent_*/submitted_* columns: the backfill has not run '
                ."(application_guardians and application_documents are both empty while {$applications} "
                .'application(s) exist). Run `php artisan applications:backfill --apply` first.'
            );
        }
    }
}
