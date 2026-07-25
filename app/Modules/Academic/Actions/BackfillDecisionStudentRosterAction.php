<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill the Decision <-> Student roster (student_decision_student) from the
 * existing student_action_logs.decision_id links (ADR-0048).
 *
 * The original action-log links are left untouched; this only ensures each
 * already-linked student is also present on the decision's coverage roster.
 * Idempotent: re-running only inserts roster rows that do not yet exist.
 *
 * Uses the query builder (not Eloquent models) so it stays stable when invoked
 * from a migration.
 */
class BackfillDecisionStudentRosterAction
{
    /**
     * @return int Number of new roster rows inserted.
     */
    public static function run(): int
    {
        $pairs = DB::table('student_action_logs')
            ->whereNotNull('decision_id')
            ->select('decision_id', 'student_id')
            ->distinct()
            ->get()
            ->groupBy('decision_id');

        $inserted = 0;

        foreach ($pairs as $decisionId => $rows) {
            $studentIds = $rows->pluck('student_id')->map(fn ($id): int => (int) $id)->unique();

            $existing = DB::table('student_decision_student')
                ->where('student_decision_id', $decisionId)
                ->pluck('student_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $missing = $studentIds->diff($existing)->values();

            if ($missing->isEmpty()) {
                continue;
            }

            $now = Carbon::now();

            DB::table('student_decision_student')->insert(
                $missing->map(fn (int $studentId): array => [
                    'student_decision_id' => (int) $decisionId,
                    'student_id' => $studentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );

            $inserted += $missing->count();
        }

        if ($inserted > 0) {
            Log::info('Decision student roster backfilled', [
                'inserted_roster_rows' => $inserted,
            ]);
        }

        return $inserted;
    }
}
