<?php

declare(strict_types=1);

namespace App\Console\Commands\Admissions;

use App\Services\Admissions\IntendedProgramNormalizer;
use Illuminate\Console\Command;

/**
 * Normalize the legacy `intended_program` text to canonical `Program.code`
 * (ADR-0005). Read-only dry-run by default; `--apply` to commit.
 */
class NormalizeIntendedProgramCommand extends Command
{
    protected $signature = 'applications:normalize-program {--apply : Write the changes (default is a read-only dry-run)}';

    protected $description = 'Normalize student_applications.intended_program from labels to canonical Program codes (ADR-0005)';

    public function handle(IntendedProgramNormalizer $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $report = $normalizer->run(dryRun: ! $apply);

        $this->info(sprintf('🎓 Intended-program normalization (%s)', $apply ? 'APPLY' : 'dry-run'));
        $this->table(['Metric', 'Count'], [
            ['Applications scanned', $report['total']],
            ['Normalized from linked Student', $report['from_student']],
            ['Mapped from known label', $report['from_label_map']],
            ['Already correct (unchanged)', $report['unchanged']],
            ['Left as-is (no student, unknown label)', $report['unmapped']],
        ]);

        if ($report['unmapped'] > 0) {
            $this->warn("{$report['unmapped']} application(s) keep a non-code intended_program (no linked student and an unknown label) — review manually.");
        }

        if ($apply) {
            $this->info('✅ Applied.');
        } else {
            $this->comment('Dry-run only — nothing written. Re-run with --apply to commit.');
        }

        return self::SUCCESS;
    }
}
