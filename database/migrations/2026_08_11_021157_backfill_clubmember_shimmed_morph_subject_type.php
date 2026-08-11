<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pilot for the deprecated-shim sweep (plan 260811-0012, phase 1). Rewrites
 * persisted `activity_log.subject_type` rows that still carry the old
 * `App\Models\ClubMember` shim FQCN to the canonical
 * `App\Modules\Engagement\Models\ClubMember`. Nothing else changes: the
 * shim itself stays in place until the module's caller sweep (phase 3).
 */
return new class extends Migration
{
    private const OLD_FQCN = 'App\Models\ClubMember';

    private const NEW_FQCN = 'App\Modules\Engagement\Models\ClubMember';

    public function up(): void
    {
        DB::table('activity_log')
            ->where('subject_type', self::OLD_FQCN)
            ->update(['subject_type' => self::NEW_FQCN]);
    }

    public function down(): void
    {
        // ponytail: only safe while the class_alias shim still exists — once a
        // later phase deletes app/Models/ClubMember.php, writing OLD_FQCN back
        // would leave rows pointing at a class that no longer resolves. Skip
        // the rewrite once the shim is gone rather than corrupt those rows.
        if (! class_exists(self::OLD_FQCN)) {
            return;
        }

        DB::table('activity_log')
            ->where('subject_type', self::NEW_FQCN)
            ->update(['subject_type' => self::OLD_FQCN]);
    }
};
