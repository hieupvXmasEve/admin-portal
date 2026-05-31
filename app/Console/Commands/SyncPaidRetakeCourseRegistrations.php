<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Student;
use App\Modules\Academic\Actions\SyncPaidRetakeRegistrationsAction;
use Illuminate\Console\Command;

class SyncPaidRetakeCourseRegistrations extends Command
{
    protected $signature = 'retake-courses:sync-paid
        {--student-code= : Limit sync to one student code}
        {--dry-run : Report eligible registrations without changing data}';

    protected $description = 'Sync paid retake course registrations from settled finance charges.';

    public function handle(SyncPaidRetakeRegistrationsAction $action): int
    {
        $studentCode = $this->option('student-code');
        $dryRun = (bool) $this->option('dry-run');

        if ($studentCode) {
            $student = Student::query()->where('student_id', $studentCode)->first();
            if (! $student) {
                $this->error("Student not found: {$studentCode}");

                return self::FAILURE;
            }

            $result = $action->runForStudent((int) $student->id, $dryRun);
        } else {
            $result = $action->runAll($dryRun);
        }

        $this->info($dryRun ? 'Dry-run complete.' : 'Sync complete.');
        $this->line("Checked: {$result['checked']}");
        $this->line("Eligible: {$result['eligible']}");
        $this->line("Synced: {$result['synced']}");
        $this->line("Waiting for class: {$result['waiting_for_class']}");
        $this->line("Skipped: {$result['skipped']}");
        $this->line("Failed: {$result['failed']}");

        if ($result['details'] !== []) {
            $this->table(
                ['Registration', 'Student', 'Charge', 'Paid', 'Balance', 'Status'],
                collect($result['details'])
                    ->map(fn (array $detail) => [
                        $detail['registration_id'] ?? '',
                        $detail['student_id'] ?? '',
                        $detail['finance_charge_id'] ?? '',
                        $detail['paid_amount'] ?? '',
                        $detail['balance'] ?? '',
                        $detail['status'] ?? '',
                    ])
                    ->all()
            );
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
