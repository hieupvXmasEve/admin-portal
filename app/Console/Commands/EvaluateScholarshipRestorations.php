<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Actions\CreateRestorationProposalAction;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Academic\ScholarshipRestorationVerdictReader;
use Illuminate\Console\Command;

/**
 * Manual-first for the pilot (validation session 1) — no scheduler. Scans
 * `applied` adjustments carrying-forward into the given semester and, per
 * the Academic verdict, proposes restoration (clean), leaves still-failing
 * cases for a NEW dossier via Phase 3 identification (not automated here),
 * or skips incomplete/appealing cases for a later run.
 */
class EvaluateScholarshipRestorations extends Command
{
    protected $signature = 'finance:evaluate-scholarship-restorations
        {--campus= : Campus ID}
        {--semester= : Semester ID whose results are being evaluated (the adjustment target semester)}
        {--actor= : User ID recorded as the proposal proposer}';

    protected $description = 'Evaluate applied scholarship adjustments for restoration in a campus/semester';

    public function handle(
        CreateRestorationProposalAction $createProposal,
        ScholarshipRestorationVerdictReader $verdictReader,
    ): int {
        $campusId = $this->option('campus');
        $semesterId = $this->option('semester');
        $actorId = $this->option('actor');

        if ($campusId === null || $semesterId === null || $actorId === null) {
            $this->error('--campus, --semester, and --actor are all required.');

            return self::FAILURE;
        }

        if (! Campus::query()->whereKey((int) $campusId)->exists()) {
            $this->error("Campus #{$campusId} does not exist.");

            return self::FAILURE;
        }

        $semester = Semester::query()->find((int) $semesterId);

        if ($semester === null || $semester->is_archived) {
            $this->error("Semester #{$semesterId} does not exist or is archived.");

            return self::FAILURE;
        }

        if (! User::query()->whereKey((int) $actorId)->exists()) {
            $this->error("Actor user #{$actorId} does not exist.");

            return self::FAILURE;
        }

        $stats = ['proposed' => 0, 'still_failing' => 0, 'not_finalized' => 0, 'skipped_existing' => 0];

        $adjustments = ScholarshipSemesterAdjustment::query()
            ->where('campus_id', (int) $campusId)
            ->where('target_semester_id', (int) $semesterId)
            ->where('status', ScholarshipSemesterAdjustment::STATUS_APPLIED)
            ->get();

        foreach ($adjustments as $adjustment) {
            $verdict = $verdictReader->verdict((int) $adjustment->student_id, (int) $semesterId);

            if ($verdict === ScholarshipRestorationVerdictReader::STILL_FAILING) {
                $stats['still_failing']++;

                continue;
            }

            if ($verdict === ScholarshipRestorationVerdictReader::NOT_FINALIZED) {
                $stats['not_finalized']++;

                continue;
            }

            try {
                $createProposal->run(
                    (int) $adjustment->id,
                    'Target semester results clean',
                    (int) $actorId,
                );
                $stats['proposed']++;
            } catch (\DomainException $e) {
                // Idempotent re-run: a proposal already active for this
                // adjustment is expected, not an error.
                $stats['skipped_existing']++;
            }
        }

        $this->info("Proposed: {$stats['proposed']}");
        $this->info("Still failing (new dossier needed): {$stats['still_failing']}");
        $this->info("Not finalized/appealing (retry next run): {$stats['not_finalized']}");
        $this->info("Skipped (existing proposal): {$stats['skipped_existing']}");

        return self::SUCCESS;
    }
}
