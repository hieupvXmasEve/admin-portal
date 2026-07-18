<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Academic\FacultyWorkforce\Actions\ReconcileLecturerAccessEligibilityAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('faculty:reconcile-lecturer-access {--lecturer-id=}')]
#[Description('Reconcile Faculty Workforce access eligibility into Identity lecturer grants.')]
final class ReconcileLecturerAccessEligibilityCommand extends Command
{
    public function handle(): int
    {
        $lecturerId = $this->option('lecturer-id');
        $count = ReconcileLecturerAccessEligibilityAction::run([
            'lecturer_id' => $lecturerId !== null ? (int) $lecturerId : null,
        ]);

        $this->info("Reconciled {$count} lecturer access grant(s).");

        return self::SUCCESS;
    }
}
