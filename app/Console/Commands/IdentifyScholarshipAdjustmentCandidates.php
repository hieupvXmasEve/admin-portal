<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\IdentifyCandidatesAction;
use Illuminate\Console\Command;

/**
 * Manual-first for the pilot (validation session 1) — no scheduler. All three
 * arguments are required and explicit; nothing is auto-derived, and this
 * command runs with NO session (artisan context), so it must never depend on
 * session('current_campus_id') anywhere in its call chain.
 */
class IdentifyScholarshipAdjustmentCandidates extends Command
{
    protected $signature = 'academic:identify-scholarship-adjustment-candidates
        {--campus= : Campus ID}
        {--source-semester= : Source semester ID (where the failure occurred)}
        {--target-semester= : Target semester ID (where the adjustment applies)}
        {--actor= : User ID recorded as the dossier creator}';

    protected $description = 'Identify scholarship adjustment candidates for a campus and semester pair';

    public function handle(IdentifyCandidatesAction $action): int
    {
        $campusId = $this->option('campus');
        $sourceSemesterId = $this->option('source-semester');
        $targetSemesterId = $this->option('target-semester');
        $actorId = $this->option('actor');

        if ($campusId === null || $sourceSemesterId === null || $targetSemesterId === null || $actorId === null) {
            $this->error('--campus, --source-semester, --target-semester, and --actor are all required.');

            return self::FAILURE;
        }

        if (! User::query()->whereKey((int) $actorId)->exists()) {
            $this->error("Actor user #{$actorId} does not exist.");

            return self::FAILURE;
        }

        try {
            $result = $action->run(
                (int) $campusId,
                (int) $sourceSemesterId,
                (int) $targetSemesterId,
                (int) $actorId,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Created: {$result['created']}");
        $this->info("Skipped (existing dossier): {$result['skipped_existing']}");
        $this->info("Excluded (is_passed not evaluated): {$result['excluded_null_is_passed']}");

        return self::SUCCESS;
    }
}
