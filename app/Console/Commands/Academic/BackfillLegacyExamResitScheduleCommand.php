<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Models\User;
use App\Modules\Academic\Actions\BackfillLegacyExamResitScheduleAction;
use Illuminate\Console\Command;

/**
 * ACAD-RET-001 — backfill exam-resit schedule for legacy-reconciled attempts.
 *
 * Run AFTER `academic:reconcile-legacy-exam-resit-fees` so attempts exist.
 */
class BackfillLegacyExamResitScheduleCommand extends Command
{
    protected $signature = 'academic:backfill-legacy-exam-resit-schedule
        {--dry-run : Report what would be written without persisting}
        {--exam-date=2026-04-20 : Legacy exam date (Y-m-d)}
        {--start-time=09:00 : Slot start time}
        {--end-time=11:00 : Slot end time}
        {--invigilator-user-id=1 : Staff user id whose lecturer profile coi thi}
        {--actor-user-id=1 : User id recorded as scheduler / invigilator assigner}';

    protected $description = 'Backfill legacy exam-resit room slots, sessions, invigilators, and scheduled attempts (ACAD-RET-001)';

    public function handle(BackfillLegacyExamResitScheduleAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $actorUserId = (int) $this->option('actor-user-id');
        $invigilatorUserId = (int) $this->option('invigilator-user-id');

        if (! User::query()->whereKey($actorUserId)->exists()) {
            $this->error("actor-user-id={$actorUserId} does not exist.");

            return self::FAILURE;
        }

        if (! User::query()->whereKey($invigilatorUserId)->exists()) {
            $this->error("invigilator-user-id={$invigilatorUserId} does not exist.");

            return self::FAILURE;
        }

        $result = $action->run(
            examDate: (string) $this->option('exam-date'),
            startTime: (string) $this->option('start-time'),
            endTime: (string) $this->option('end-time'),
            invigilatorUserId: $invigilatorUserId,
            actorUserId: $actorUserId,
            dryRun: $dryRun,
        );

        if ($result['checked'] === 0) {
            $this->info('No legacy exam-resit attempts need schedule backfill.');

            return self::SUCCESS;
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info(sprintf(
            '%sChecked %d legacy attempt(s): %d scheduled, %d skipped.',
            $prefix,
            $result['checked'],
            $result['scheduled'],
            $result['skipped'],
        ));
        $this->line(sprintf(
            '%sSlots created: %d, sessions created: %d, invigilators assigned: %d.',
            $prefix,
            $result['slots_created'],
            $result['sessions_created'],
            $result['invigilators_assigned'],
        ));

        return self::SUCCESS;
    }
}