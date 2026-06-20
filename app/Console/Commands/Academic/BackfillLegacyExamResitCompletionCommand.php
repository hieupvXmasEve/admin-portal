<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Models\User;
use App\Modules\Academic\Actions\BackfillLegacyExamResitCompletionAction;
use Illuminate\Console\Command;

class BackfillLegacyExamResitCompletionCommand extends Command
{
    protected $signature = 'academic:backfill-legacy-exam-resit-completion
        {--dry-run : Report what would be written without persisting}
        {--completed-at=2026-04-20 11:00:00 : Completion timestamp for attempts and sessions}
        {--actor-user-id=1 : User id recorded on completion snapshots}
        {--exclude-student-codes=AUH12910 : Comma-separated student codes to skip}';

    protected $description = 'Complete legacy exam-resit attempts from current academic scores and close schedule artifacts (ACAD-RET-001)';

    public function handle(BackfillLegacyExamResitCompletionAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $actorUserId = (int) $this->option('actor-user-id');

        if (! User::query()->whereKey($actorUserId)->exists()) {
            $this->error("actor-user-id={$actorUserId} does not exist.");

            return self::FAILURE;
        }

        $excludeCodes = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $this->option('exclude-student-codes')),
        )));

        $result = $action->run(
            completedAt: (string) $this->option('completed-at'),
            actorUserId: $actorUserId,
            excludeStudentCodes: $excludeCodes,
            dryRun: $dryRun,
        );

        if ($result['checked'] === 0 && $result['slots_closed'] === 0) {
            $this->info('No legacy exam-resit attempts need completion backfill.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d attempt(s): %d completed, %d skipped.',
            $prefix,
            $result['checked'],
            $result['completed'],
            $result['skipped'],
        ));
        $this->line(sprintf(
            '%sClosed %d slot(s) and %d session(s).',
            $prefix,
            $result['slots_closed'],
            $result['sessions_closed'],
        ));

        return self::SUCCESS;
    }
}