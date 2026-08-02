<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\MarkOverdueAction;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Progression\Support\ScholarshipStaffNotificationPublisher;
use Illuminate\Console\Command;

/**
 * Flip still-pending scholarship-adjustment confirmations to `overdue` once
 * more than one calendar day (app timezone, Asia/Saigon) has passed since the
 * confirmation was requested. Global console command — the Academic module has
 * no Console dir (consistent with IdentifyScholarshipAdjustmentCandidates).
 *
 * The confirmed-vs-overdue race is guarded inside MarkOverdueAction (no-op when
 * confirmed_at is already set), so a student confirming seconds before this run
 * is never flipped to overdue.
 */
class MarkScholarshipConfirmationsOverdue extends Command
{
    protected $signature = 'academic:mark-scholarship-confirmations-overdue';

    protected $description = 'Mark pending scholarship-adjustment confirmations overdue after 1 calendar day';

    public function handle(): int
    {
        // now() is in the app timezone (config('app.timezone')); a confirmation
        // requested at or before "one day ago" is past its 1-calendar-day window.
        $cutoff = now()->subDay();

        $dossiers = ScholarshipAdjustmentDossier::query()
            ->where('confirmation_status', ScholarshipAdjustmentDossier::CONFIRMATION_PENDING)
            ->whereNotNull('confirmation_requested_at')
            ->where('confirmation_requested_at', '<=', $cutoff)
            ->get();

        $marked = 0;

        foreach ($dossiers as $dossier) {
            $updated = MarkOverdueAction::run($dossier);

            if ($updated->confirmation_status === ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE) {
                $marked++;

                // Overdue is what lets an approver override the confirmation
                // gate, so this transition must never happen silently.
                app(ScholarshipStaffNotificationPublisher::class)->confirmationOverdue($updated);
            }
        }

        $this->info("Marked {$marked} confirmation(s) overdue.");

        return self::SUCCESS;
    }
}
