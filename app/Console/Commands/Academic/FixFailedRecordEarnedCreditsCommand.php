<?php

declare(strict_types=1);

namespace App\Console\Commands\Academic;

use App\Models\AcademicRecord;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Corrects the sibling-field drift left by the unattributed is_passed batch:
 * rows where a failed unit (is_passed = false) still carries earned credits.
 * A failed unit earns no credits, so both credit_points_earned and its sibling
 * credit_hours_earned are set to 0 on academic_records; transcript_entries has
 * only credit_points_earned. This matches the resit writer's paired invariant
 * ("failed => both earned columns zero").
 *
 * Dry-run-first per the repo convention (cf. ApplyGradingSchemePackCommand):
 * operators run --dry-run to confirm the affected rows, then rerun with --commit.
 * Targets is_passed = false only; the 212 completed-but-failed rows and every
 * passing row are untouched.
 */
final class FixFailedRecordEarnedCreditsCommand extends Command
{
    protected $signature = 'academic:fix-failed-record-earned-credits
        {--dry-run : Report affected rows without saving}
        {--commit : Zero credit_points_earned on the affected rows}';

    protected $description = 'Zero credit_points_earned on failed units (is_passed = false) in academic_records and transcript_entries';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $dryRun = (bool) $this->option('dry-run');

        if ($commit === $dryRun) {
            $this->error('Pass exactly one of --dry-run or --commit.');

            return self::FAILURE;
        }

        // academic_records: a failed unit must carry neither earned points nor
        // earned hours. transcript_entries has only the points column.
        $records = AcademicRecord::query()->where('is_passed', false)
            ->where(fn ($q) => $q->where('credit_points_earned', '>', 0)->orWhere('credit_hours_earned', '>', 0));
        $entries = TranscriptEntry::query()->where('is_passed', false)->where('credit_points_earned', '>', 0);

        $recordIds = $records->pluck('id')->all();
        $entryIds = $entries->pluck('id')->all();

        $this->info(sprintf(
            'Failed units carrying earned credits: %d academic_records, %d transcript_entries.',
            count($recordIds),
            count($entryIds),
        ));
        if ($recordIds !== []) {
            $this->line('  academic_records ids: '.implode(', ', $recordIds));
        }
        if ($entryIds !== []) {
            $this->line('  transcript_entries ids: '.implode(', ', $entryIds));
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes written. Rerun with --commit to apply.');

            return self::SUCCESS;
        }

        $recordsUpdated = $records->update(['credit_points_earned' => 0, 'credit_hours_earned' => 0]);
        $entriesUpdated = $entries->update(['credit_points_earned' => 0]);

        // Mass update bypasses the models' audit trail, so record the correction here.
        Log::info('Zeroed earned credits on failed units', [
            'academic_record_ids' => $recordIds,
            'transcript_entry_ids' => $entryIds,
            'academic_records_updated' => $recordsUpdated,
            'transcript_entries_updated' => $entriesUpdated,
        ]);

        $this->info("Committed: {$recordsUpdated} academic_records (points + hours), {$entriesUpdated} transcript_entries (points) set to 0 earned.");

        return self::SUCCESS;
    }
}
