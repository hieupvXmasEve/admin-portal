<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\Placement;

use App\Modules\Academic\Progression\Exceptions\InvalidProgressionState;
use App\Modules\Academic\Progression\Support\BulkEgcPlacementRowMapper;
use App\Shared\Contracts\Admissions\IntakeSemesterReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class ImportBulkEgcPlacementFromCsvAction
{
    private const MAX_IMPORT_ROWS = 1000;

    public function __construct(
        private readonly BulkEgcPlacementRowMapper $mapper,
        private readonly IntakeSemesterReader $intakeSemester,
    ) {}

    public function preview(UploadedFile $file): array
    {
        return $this->process($file, false, null);
    }

    public function execute(UploadedFile $file, int $userId): array
    {
        return $this->process($file, true, $userId);
    }

    private function process(UploadedFile $file, bool $shouldExecute, ?int $userId): array
    {
        $semesterId = $this->intakeSemester->currentIntakeSemesterId();
        if ($semesterId === null) {
            return $this->emptyResponse('Intake semester is not configured. Set it at /student-applications/crm-mappings first.');
        }

        $sheet = $this->getFirstSheet($file);
        if (empty($sheet)) {
            return $this->emptyResponse('File is empty.');
        }

        $headerValidation = $this->mapper->validateHeaders($sheet[0] ?? []);
        if (! ($headerValidation['ok'] ?? false)) {
            return $this->emptyResponse('Could not find required columns: '.implode(', ', $headerValidation['missing'] ?? []).'.');
        }

        $dataRows = array_slice($sheet, 1, null, true);
        $dataRows = array_filter($dataRows, fn (array $row) => ! $this->isRowEmpty($row));
        if (count($dataRows) > self::MAX_IMPORT_ROWS) {
            return $this->emptyResponse('Too many rows. Maximum allowed is '.self::MAX_IMPORT_ROWS.'.');
        }

        $campusId = (int) session('current_campus_id');

        $rows = [];
        $validCount = 0;
        $skipCounts = [];
        $success = 0;
        $failed = 0;

        foreach ($dataRows as $index => $row) {
            $rowNumber = (int) $index + 1;
            $mapped = $this->mapper->mapRow($row, $rowNumber, $headerValidation['student_id_col'], $headerValidation['level_col'], $campusId);

            if ($mapped['status'] === 'skip') {
                $skipCounts[$mapped['skip_reason']] = ($skipCounts[$mapped['skip_reason']] ?? 0) + 1;
                $rows[] = $mapped;

                continue;
            }

            $validCount++;

            if (! $shouldExecute) {
                $rows[] = $mapped;

                continue;
            }

            try {
                InitializeStudentPlacementAction::run([
                    'student_id' => $mapped['normalized_payload']['student_id'],
                    'semester_id' => $semesterId,
                    'has_ielts' => false,
                    'english_level' => $mapped['normalized_payload']['english_level'],
                    'notes' => "Bulk EGC import ({$file->getClientOriginalName()})",
                    'created_by_user_id' => $userId,
                ]);
                $success++;
                $rows[] = [...$mapped, 'status' => 'success'];
            } catch (InvalidProgressionState $e) {
                $failed++;
                $rows[] = [...$mapped, 'status' => 'error', 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                Log::error('Bulk EGC placement import row failed', ['row_number' => $rowNumber, 'error' => $e->getMessage()]);
                $failed++;
                $rows[] = [...$mapped, 'status' => 'error', 'error' => 'Import failed on this row due to a business validation error.'];
            }
        }

        return [
            'summary' => [
                'total' => count($dataRows),
                'valid' => $validCount,
                'skipped' => count($dataRows) - $validCount,
                'skip_counts' => $skipCounts,
                'success' => $shouldExecute ? $success : 0,
                'failed' => $shouldExecute ? $failed : 0,
            ],
            'header' => $headerValidation,
            'rows' => $rows,
            'global_errors' => [],
        ];
    }

    private function getFirstSheet(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new class implements ToArray
        {
            public function array(array $array): void {}
        }, $file);

        return $sheets[0] ?? [];
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function emptyResponse(string $message): array
    {
        return [
            'summary' => [
                'total' => 0,
                'valid' => 0,
                'skipped' => 0,
                'skip_counts' => [],
                'success' => 0,
                'failed' => 0,
            ],
            'header' => ['ok' => false],
            'rows' => [],
            'global_errors' => [$message],
        ];
    }
}
