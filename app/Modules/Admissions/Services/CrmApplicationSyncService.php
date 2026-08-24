<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Services;

use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\UpsertCrmApplicationAction;
use App\Modules\Admissions\Integrations\Crm\CrmClient;
use App\Modules\Admissions\Support\Crm\CrmApplicationMapper;
use App\Services\Admissions\Exceptions\ApplicationFrozenException;
use App\Services\ApplicationDocumentTypeSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Fetches the CRM `/api/ne` batch and upserts each record through the live
 * write path, one record per transaction so a single bad record never aborts
 * the run. `--dry-run` computes the planned action per record without
 * invoking the write path at all — not an outer rollback, which would swallow
 * the doc-type seeder's own nested transaction and hold locks across the
 * whole HTTP fetch, and never writes, so it does not need the write lock.
 */
final class CrmApplicationSyncService
{
    /** Shared with {@see CrmMappingResolver} so the two writers cannot interleave. */
    public const LOCK_NAME = 'admissions:crm-ne-sync';

    public function __construct(
        private readonly CrmClient $client,
        private readonly CrmApplicationMapper $mapper,
        private readonly ApplicationDocumentTypeSyncService $documentTypeSync,
        private readonly CrmMappingResolver $mappingResolver,
    ) {}

    /**
     * @return array{
     *     total: int, created: int, updated: int, skipped: int, failed: int,
     *     elapsed_seconds: float,
     *     failures: list<array{student_code: string|null, error_class: string, field: string|null}>,
     * }
     */
    public function run(bool $dryRun, ?int $limit): array
    {
        $startedAt = microtime(true);

        $result = $dryRun
            ? $this->processAll($this->fetchRecords($limit), true)
            : Cache::lock(self::LOCK_NAME, 600)->block(10, function () use ($limit): array {
                $this->documentTypeSync->sync([
                    ['code' => 'id_card_back', 'name' => 'ID card (back)'],
                    ['code' => 'scholarship_certificate', 'name' => 'Scholarship certificate'],
                ]);

                $result = $this->processAll($this->fetchRecords($limit), false);

                // Resolve AFTER upserting, not before: it backfills the derived
                // campus_code/intended_program/intake columns across every null
                // pending row, so the records just inserted this run are mapped
                // now instead of waiting for the next sync. A single trailing
                // pass also covers rows whose mapping was added since last run.
                $this->mappingResolver->resolveWithoutLocking();

                return $result;
            });

        return [...$result, 'elapsed_seconds' => round(microtime(true) - $startedAt, 2)];
    }

    /** @return list<array<string, mixed>> */
    private function fetchRecords(?int $limit): array
    {
        $records = $this->client->fetchNewEnrollments();

        return $limit !== null ? array_slice($records, 0, $limit) : $records;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array{total: int, created: int, updated: int, skipped: int, failed: int, failures: list<array<string, mixed>>}
     */
    private function processAll(array $records, bool $dryRun): array
    {
        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'failures' => []];

        foreach ($records as $record) {
            $result['total']++;
            $this->processRecord($record, $dryRun, $result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array{total: int, created: int, updated: int, skipped: int, failed: int, failures: list<array<string, mixed>>}  $result
     */
    private function processRecord(array $record, bool $dryRun, array &$result): void
    {
        $studentCode = is_string($record['student_code'] ?? null) ? $record['student_code'] : null;

        try {
            $payload = [...$this->mapper->map($record), 'source' => 'ne'];

            if ($dryRun) {
                $exists = filled($studentCode) && StudentApplication::query()->where('student_code', $studentCode)->exists();
                $exists ? $result['updated']++ : $result['created']++;

                return;
            }

            $upserted = UpsertCrmApplicationAction::run($payload);
            $upserted['created'] ? $result['created']++ : $result['updated']++;
        } catch (ApplicationFrozenException) {
            $result['skipped']++;
            Log::info('CRM NE sync: skipped a non-pending application.', ['student_code' => $studentCode]);
        } catch (InvalidArgumentException $exception) {
            $result['failed']++;
            $result['failures'][] = ['student_code' => $studentCode, 'error_class' => InvalidArgumentException::class, 'field' => $exception->getMessage()];
            Log::warning('CRM NE sync: record failed field validation.', ['student_code' => $studentCode, 'field' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            $result['failed']++;
            $result['failures'][] = ['student_code' => $studentCode, 'error_class' => $exception::class, 'field' => null];
            Log::error('CRM NE sync: record failed unexpectedly.', ['student_code' => $studentCode, 'error_class' => $exception::class]);
        }
    }
}
