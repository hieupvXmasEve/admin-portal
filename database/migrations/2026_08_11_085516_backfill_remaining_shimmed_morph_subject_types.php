<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 of the deprecated-shim sweep (plan 260811-0012). Rewrites the
 * remaining `activity_log.subject_type` rows that still carry a shimmed
 * `App\Models\<X>` FQCN to their canonical `App\Modules\<Owner>\Models\<X>`
 * namespace. `ClubMember` was already migrated by phase 1's pilot; this
 * migration covers the other four models the full enumeration found.
 *
 * Deployed alone, with every shim file still in place, so both the old and
 * new FQCN resolve throughout the deploy window. See plan.md "Nothing can
 * write a shimmed FQCN anymore" — these rows are frozen historical residue,
 * not an accruing set, so ordering this ahead of the caller sweep is safe.
 */
return new class extends Migration
{
    private const MAPPINGS = [
        'App\Models\Room' => 'App\Modules\Facilities\Models\Room',
        'App\Models\RoomBooking' => 'App\Modules\Facilities\Models\RoomBooking',
        'App\Models\Club' => 'App\Modules\Engagement\Models\Club',
        'App\Models\Building' => 'App\Modules\Facilities\Models\Building',
    ];

    public function up(): void
    {
        foreach (self::MAPPINGS as $oldFqcn => $newFqcn) {
            DB::table('activity_log')
                ->where('subject_type', $oldFqcn)
                ->update(['subject_type' => $newFqcn]);
        }
    }

    public function down(): void
    {
        // ponytail: irreversible by design. up() cannot distinguish rows it
        // rewrote from rows the application wrote canonically on its own since
        // the 2026-08-09 model move, so a reversal would corrupt the latter.
        // Recovery is to revert application code, not data — canonical FQCNs
        // resolve under both namespaces while the shims exist.
        throw new RuntimeException(
            'Irreversible morph backfill. Revert application code instead; '
            .'canonical FQCNs resolve under both namespaces while the shims exist.'
        );
    }
};
