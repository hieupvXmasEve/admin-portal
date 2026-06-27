<?php

declare(strict_types=1);

namespace App\Services\Admissions;

use App\Models\ApplicationDocument;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Services\ApplicationDocumentTypeSyncService;
use App\Services\ApplicationGuardianService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * One-time migration of the legacy `student_applications` shape into the
 * decomposed structure (slice 09), runnable as a dry-run before committing.
 *
 * For every existing Application it:
 *  - moves the thin `parent_phone` / `parent_email` pair into a single primary
 *    {@see ApplicationGuardian} (the legacy pair carried no name, so
 *    a placeholder name is used);
 *  - builds {@see ApplicationDocument} rows — authoritatively from the admissions
 *    CRM export (`Asia_NE_2025_AdmissionFiles.csv`, joined by `student_code`) for
 *    Applications present there, falling back to the sparse `submitted_*` URL
 *    columns for Applications the export does not cover;
 *  - maps `status` to the new lifecycle: converted rows (a linked `student_id`)
 *    become `enrolled`, the rest become `pending` (auto-approve is gone).
 *
 * The document-type catalog is seeded first (from `Asia_File_Types.csv`) so
 * documents denormalize a real `file_type_name` and the `file_id` → catalog
 * code mapping is available.
 *
 * Idempotent: CSV documents upsert by `crm_file_id`, legacy documents are keyed
 * by (Application, type, link), a guardian is only created when the Application
 * has none, and the status remap is a no-op once applied — so a re-run reports
 * zero further changes. The legacy columns are *read*, never cleared; dropping
 * them is the job of the accompanying schema migration, run afterwards.
 */
class ApplicationBackfillService
{
    /**
     * Placeholder name for guardians reconstructed from the legacy `parent_*`
     * pair, which never carried a name. ("Phụ huynh" = "parent/guardian".)
     */
    public const LEGACY_GUARDIAN_NAME = 'Phụ huynh';

    /**
     * Legacy `submitted_*` URL column => document-type code. The first six map
     * onto catalog codes; `insurance_card` / `exemption_gc` have no catalog entry
     * and keep a self-describing code so no information is lost.
     *
     * @var array<string, string>
     */
    private const LEGACY_DOCUMENT_MAP = [
        'submitted_photo' => 'student_photo',
        'submitted_cccd' => 'id_card_front',
        'submitted_ccta' => 'english_certificate',
        'submitted_tn_translate' => 'diploma',
        'submitted_hb_translate' => 'transcript',
        'submitted_other' => 'other_achievements',
        'submitted_insurance_card' => 'insurance_card',
        'submitted_exemption_gc' => 'exemption_gc',
    ];

    public function __construct(
        private readonly ApplicationDocumentTypeSyncService $catalogSync,
        private readonly ApplicationGuardianService $guardians,
    ) {}

    /**
     * Run the backfill. When `$dryRun` is true every write happens inside a
     * transaction that is rolled back before returning, so the report reflects
     * the planned changes while the database is left untouched.
     */
    public function run(string $fileTypesCsvPath, string $admissionFilesCsvPath, bool $dryRun): BackfillReport
    {
        $catalog = $this->readCsv($fileTypesCsvPath);
        [$idToCode, $codeToName] = $this->catalogMaps($catalog);
        $filesByStudentCode = $this->groupAdmissionFiles($this->readCsv($admissionFilesCsvPath));

        DB::beginTransaction();

        try {
            $catalogSynced = $this->catalogSync->sync($this->catalogEntries($catalog));

            $guardiansCreated = 0;
            $documentsFromCsv = 0;
            $documentsFromLegacy = 0;
            $withParentData = 0;
            $matchedInCsv = 0;
            $total = 0;

            StudentApplication::query()->orderBy('id')->chunkById(500, function (Collection $applications) use (
                &$guardiansCreated, &$documentsFromCsv, &$documentsFromLegacy, &$withParentData, &$matchedInCsv, &$total,
                $filesByStudentCode, $idToCode, $codeToName
            ): void {
                foreach ($applications as $application) {
                    $total++;

                    if ($this->hasParentData($application)) {
                        $withParentData++;
                        $guardiansCreated += $this->backfillGuardian($application);
                    }

                    $studentCode = trim((string) $application->student_code);
                    $csvRows = $studentCode !== '' ? ($filesByStudentCode[$studentCode] ?? null) : null;

                    if ($csvRows !== null) {
                        $matchedInCsv++;
                        foreach ($csvRows as $row) {
                            $documentsFromCsv += $this->upsertCsvDocument($application, $row, $idToCode, $codeToName);
                        }
                    } else {
                        $documentsFromLegacy += $this->moveLegacyDocuments($application, $codeToName);
                    }
                }
            });

            [$toEnrolled, $toPending] = $this->remapStatuses();

            $report = new BackfillReport(
                applied: ! $dryRun,
                applicationsTotal: $total,
                catalogSynced: $catalogSynced,
                guardiansCreated: $guardiansCreated,
                documentsFromCsv: $documentsFromCsv,
                documentsFromLegacy: $documentsFromLegacy,
                statusToEnrolled: $toEnrolled,
                statusToPending: $toPending,
                applicationsWithParentData: $withParentData,
                applicationsMatchedInCsv: $matchedInCsv,
            );

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $report;
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Whether the Application still carries a value in either legacy parent
     * column. Guarded by {@see Schema::hasColumn()} so the service is safe to call
     * after the columns have been dropped (it simply finds nothing to move).
     */
    private function hasParentData(StudentApplication $application): bool
    {
        if (! Schema::hasColumn('student_applications', 'parent_phone')) {
            return false;
        }

        return ! empty($application->parent_phone) || ! empty($application->parent_email);
    }

    /**
     * Create a single primary guardian from the legacy `parent_*` pair, unless
     * the Application already has guardians (idempotent re-run).
     *
     * @return int 1 when a guardian was created, 0 otherwise
     */
    private function backfillGuardian(StudentApplication $application): int
    {
        if ($application->guardians()->exists()) {
            return 0;
        }

        $this->guardians->create($application, [
            'full_name' => self::LEGACY_GUARDIAN_NAME,
            'relationship' => null,
            'phone' => $application->parent_phone,
            'email' => $application->parent_email,
            'is_primary' => true,
        ]);

        return 1;
    }

    /**
     * Upsert one CRM-export document row by its globally-unique file id.
     *
     * @param  array<string, string|null>  $row
     * @param  array<int, string>  $idToCode
     * @param  array<string, string|null>  $codeToName
     * @return int 1 when a new document row was created, 0 when an existing one was updated
     */
    private function upsertCsvDocument(StudentApplication $application, array $row, array $idToCode, array $codeToName): int
    {
        $crmFileId = trim((string) ($row['id'] ?? ''));

        if ($crmFileId === '') {
            return 0;
        }

        $fileId = (int) ($row['file_id'] ?? 0);
        $code = $idToCode[$fileId] ?? ('asia_file_type_'.$fileId);
        $size = is_numeric($row['size'] ?? null) ? (int) $row['size'] : null;

        $document = ApplicationDocument::query()->updateOrCreate(
            ['crm_file_id' => $crmFileId],
            [
                'student_application_id' => $application->id,
                'file_type_code' => $code,
                'file_type_name' => $codeToName[$code] ?? null,
                'page_index' => (int) ($row['stt_file'] ?? 0),
                'original_name' => $this->blankToNull($row['original_name'] ?? null),
                'link' => $this->driveLink($row),
                'mime_type' => $this->blankToNull($row['mime_type'] ?? null),
                'size' => $size,
                'status' => $this->blankToNull($row['status'] ?? null),
            ],
        );

        return $document->wasRecentlyCreated ? 1 : 0;
    }

    /**
     * Move whichever legacy `submitted_*` URL columns are populated into
     * documents, keyed by (Application, type, link) so a re-run does not
     * duplicate them. Used only for Applications absent from the CRM export.
     *
     * @param  array<string, string|null>  $codeToName
     * @return int the number of document rows created
     */
    private function moveLegacyDocuments(StudentApplication $application, array $codeToName): int
    {
        if (! Schema::hasColumn('student_applications', 'submitted_cccd')) {
            return 0;
        }

        $created = 0;

        foreach (self::LEGACY_DOCUMENT_MAP as $column => $code) {
            $url = trim((string) ($application->{$column} ?? ''));

            // Real legacy values are Drive URLs; '0'/'1' are boolean artefacts
            // from synthetic data and carry no document.
            if ($url === '' || $url === '0' || $url === '1') {
                continue;
            }

            $alreadyMoved = $application->documents()
                ->where('file_type_code', $code)
                ->where('link', $url)
                ->exists();

            if ($alreadyMoved) {
                continue;
            }

            $application->documents()->create([
                'crm_file_id' => null,
                'file_type_code' => $code,
                'file_type_name' => $codeToName[$code] ?? null,
                'page_index' => 0,
                'link' => $url,
                'status' => 'active',
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Map statuses to the new lifecycle: converted rows (a linked student)
     * become `enrolled`; unconverted rows become `pending` — except an existing
     * `rejected` row, which is a real terminal decision and is left untouched so
     * the rejection is not silently lost.
     *
     * @return array{0: int, 1: int} counts [toEnrolled, toPending]
     */
    private function remapStatuses(): array
    {
        $toEnrolledQuery = fn () => StudentApplication::query()
            ->whereNotNull('student_id')
            ->where('status', '!=', StudentApplication::STATUS_ENROLLED);

        $toPendingQuery = fn () => StudentApplication::query()
            ->whereNull('student_id')
            ->whereNotIn('status', [
                StudentApplication::STATUS_PENDING,
                StudentApplication::STATUS_REJECTED,
            ]);

        $toEnrolled = $toEnrolledQuery()->count();
        $toPending = $toPendingQuery()->count();

        // Bulk query-builder updates: no model events, so the one-time migration
        // does not flood the activity log with 200+ "updated" entries.
        $toEnrolledQuery()->update(['status' => StudentApplication::STATUS_ENROLLED]);
        $toPendingQuery()->update(['status' => StudentApplication::STATUS_PENDING]);

        return [$toEnrolled, $toPending];
    }

    /**
     * Build the `file_id` → catalog-code and code → catalog-name lookups from the
     * parsed file-types CSV.
     *
     * @param  array<int, array<string, string|null>>  $catalog
     * @return array{0: array<int, string>, 1: array<string, string|null>}
     */
    private function catalogMaps(array $catalog): array
    {
        $idToCode = [];
        $codeToName = [];

        foreach ($catalog as $row) {
            $id = (int) ($row['id'] ?? 0);
            $code = trim((string) ($row['code'] ?? ''));

            if ($id <= 0 || $code === '') {
                continue;
            }

            $idToCode[$id] = $code;
            $codeToName[$code] = $this->blankToNull($row['name'] ?? null);
        }

        return [$idToCode, $codeToName];
    }

    /**
     * Project parsed catalog rows onto the document-type sync attributes.
     *
     * @param  array<int, array<string, string|null>>  $catalog
     * @return array<int, array<string, mixed>>
     */
    private function catalogEntries(array $catalog): array
    {
        $entries = [];

        foreach ($catalog as $row) {
            $code = trim((string) ($row['code'] ?? ''));

            if ($code === '') {
                continue;
            }

            $entries[] = [
                'code' => $code,
                'name' => (string) ($row['name'] ?? ''),
                'type' => $this->blankToNull($row['type'] ?? null),
                'required' => (bool) (int) ($row['required'] ?? 0),
                'int_required' => (bool) (int) ($row['int_required'] ?? 0),
                'active' => (bool) (int) ($row['active'] ?? 1),
                'order' => (int) ($row['order'] ?? 0),
                'step' => $this->blankToNull($row['step'] ?? null),
            ];
        }

        return $entries;
    }

    /**
     * Group CRM-export document rows by `student_code` (the join key onto
     * Applications).
     *
     * @param  array<int, array<string, string|null>>  $rows
     * @return array<string, array<int, array<string, string|null>>>
     */
    private function groupAdmissionFiles(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $code = trim((string) ($row['student_code'] ?? ''));

            if ($code !== '') {
                $grouped[$code][] = $row;
            }
        }

        return $grouped;
    }

    /**
     * The external link for a CRM-export row: a Drive preview URL built from the
     * Google file id, matching the URL shape the legacy `submitted_*` columns
     * already used. Falls back to the stored file name when no Drive id exists.
     *
     * @param  array<string, string|null>  $row
     */
    private function driveLink(array $row): string
    {
        $driveId = trim((string) ($row['gg_file_id'] ?? ''));

        if ($driveId !== '') {
            return "https://drive.google.com/file/d/{$driveId}/preview";
        }

        return (string) ($row['name_file'] ?? '');
    }

    /**
     * Parse a CSV into a list of header-keyed associative rows.
     *
     * @return array<int, array<string, string|null>>
     */
    private function readCsv(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Backfill CSV not found: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open backfill CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                return [];
            }

            $header = array_map(static fn ($value): string => trim((string) $value), $header);
            $rows = [];

            while (($data = fgetcsv($handle)) !== false) {
                // Skip blank trailing lines.
                if ($data === [null] || $data === [false] || $data === ['']) {
                    continue;
                }

                $row = [];
                foreach ($header as $index => $key) {
                    $row[$key] = $data[$index] ?? null;
                }

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return ($value === null || $value === '') ? null : $value;
    }
}
