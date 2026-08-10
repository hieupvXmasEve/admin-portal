<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Console;

use App\Modules\Admissions\Services\CrmApplicationSyncService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

/**
 * Manual-only for now (validation V1): no scheduler entry, no UI button.
 * `Isolatable` (with `--isolated`) prevents two operators — or an impatient
 * re-run — from overlapping and racing the Phase 4 mapping backfill over the
 * same rows.
 */
final class SyncCrmApplicationsCommand extends Command implements Isolatable
{
    protected $signature = 'admissions:sync-crm-ne {--dry-run : Compute the planned action per record without writing} {--limit= : Process at most this many records}';

    protected $description = 'Pull New Enrollment applicant records from the CRM into student_applications.';

    public function handle(CrmApplicationSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $result = $service->run($dryRun, $limit);

        $this->table(
            ['Total', 'Created', 'Updated', 'Skipped', 'Failed', 'Elapsed (s)'],
            [[$result['total'], $result['created'], $result['updated'], $result['skipped'], $result['failed'], $result['elapsed_seconds']]],
        );

        if ($result['failures'] !== []) {
            $this->table(
                ['Student code', 'Error', 'Field'],
                array_map(fn (array $failure): array => [$failure['student_code'] ?? '(none)', $failure['error_class'], $failure['field'] ?? '-'], $result['failures']),
            );
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
