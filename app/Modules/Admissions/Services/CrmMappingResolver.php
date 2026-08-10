<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Services;

use App\Models\StudentApplication;
use App\Modules\Admissions\Models\CrmValueMapping;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Backfills resolved mapping values onto pending applications, run at two
 * moments through this one service: on mapping save, and at the start of
 * each sync run. Fills only null derived columns — never overwrites a value
 * a staff member set by hand. Shares {@see CrmApplicationSyncService::LOCK_NAME}
 * with the sync command so the two writers cannot interleave a full-row
 * `save()` with a targeted column update.
 */
final class CrmMappingResolver
{
    /** @var array<string, string> kind => target column on student_applications */
    private const KIND_TARGET_COLUMNS = [
        CrmValueMapping::KIND_CAMPUS => 'campus_code',
        CrmValueMapping::KIND_MAJOR => 'intended_program',
    ];

    /** @var array<string, string> kind => source raw column on student_applications */
    private const KIND_SOURCE_COLUMNS = [
        CrmValueMapping::KIND_CAMPUS => 'crm_campus',
        CrmValueMapping::KIND_MAJOR => 'crm_major',
    ];

    public function __construct(private readonly CrmMappingSettings $settings) {}

    /**
     * Acquires the shared lock itself. Callers that already hold it (the sync
     * service, for its own start-of-run resolve) must call
     * {@see self::resolveWithoutLocking()} instead — Cache locks are not
     * reentrant, so a second acquisition attempt by the same process would
     * report a false "sync in progress" skip.
     *
     * @return array{skipped: bool, updated: int}
     */
    public function resolve(): array
    {
        $lock = Cache::lock(CrmApplicationSyncService::LOCK_NAME, 30);

        if (! $lock->get()) {
            return ['skipped' => true, 'updated' => 0];
        }

        try {
            return ['skipped' => false, 'updated' => $this->resolveWithoutLocking()];
        } finally {
            $lock->release();
        }
    }

    public function resolveWithoutLocking(): int
    {
        return $this->resolveMappedValues() + $this->resolveIntake();
    }

    private function resolveMappedValues(): int
    {
        $updated = 0;

        $mappings = CrmValueMapping::query()
            ->whereIn('kind', array_keys(self::KIND_TARGET_COLUMNS))
            ->whereNotNull('local_code')
            ->get(['kind', 'crm_value', 'local_code']);

        foreach ($mappings as $mapping) {
            $targetColumn = self::KIND_TARGET_COLUMNS[$mapping->kind];
            $sourceColumn = self::KIND_SOURCE_COLUMNS[$mapping->kind];

            $updated += StudentApplication::query()
                ->where('status', StudentApplication::STATUS_PENDING)
                ->whereNull($targetColumn)
                ->where($sourceColumn, $mapping->crm_value)
                ->update([$targetColumn => $mapping->local_code]);
        }

        return $updated;
    }

    private function resolveIntake(): int
    {
        $intakeCode = $this->settings->getIntakeCode();

        if ($intakeCode === null) {
            return 0;
        }

        return StudentApplication::query()
            ->where('status', StudentApplication::STATUS_PENDING)
            ->whereNull('intake')
            ->update(['intake' => $intakeCode]);
    }
}
