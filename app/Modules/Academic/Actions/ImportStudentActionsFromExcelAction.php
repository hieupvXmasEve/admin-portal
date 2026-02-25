<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\StudentActionLog;
use App\Modules\Academic\Support\StudentActionImportConflictValidator;
use App\Modules\Academic\Support\StudentActionExcelRowMapper;
use App\Modules\Academic\Support\StudentActionPreservePreviewResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ImportStudentActionsFromExcelAction
{
    private const MAX_IMPORT_ROWS = 1000;

    public function __construct(
        private readonly StudentActionExcelRowMapper $mapper,
        private readonly StudentActionImportConflictValidator $conflictValidator,
        private readonly StudentActionPreservePreviewResolver $preservePreviewResolver
    ) {}

    public function preview(UploadedFile $file, ?int $sharedUploadRecordId): array
    {
        return $this->process($file, $sharedUploadRecordId, 0, false);
    }

    public function execute(UploadedFile $file, ?int $sharedUploadRecordId, int $userId): array
    {
        return $this->process($file, $sharedUploadRecordId, $userId, true);
    }

    private function process(UploadedFile $file, ?int $sharedUploadRecordId, int $userId, bool $shouldExecute): array
    {
        $sheet = $this->getFirstSheet($file);
        if (empty($sheet)) {
            return $this->emptyResponse('File is empty.');
        }

        $headerValidation = $this->mapper->validateHeaders($sheet[0] ?? []);
        if (! ($headerValidation['ok'] ?? false)) {
            return [
                'summary' => [
                    'total' => 0,
                    'valid' => 0,
                    'invalid' => 0,
                    'success' => 0,
                    'failed' => 0,
                ],
                'header' => $headerValidation,
                'rows' => [],
                'global_errors' => ['Template header mismatch. Please download the official template.'],
            ];
        }

        $rows = [];
        $sheetRows = array_slice($sheet, 1, null, true);
        $dataRows = array_filter($sheetRows, fn (array $row) => ! $this->isRowEmpty($row));
        if (count($dataRows) > self::MAX_IMPORT_ROWS) {
            return $this->emptyResponse('Too many rows. Maximum allowed is ' . self::MAX_IMPORT_ROWS . '.');
        }

        $validRows = 0;
        $invalidRows = 0;
        $success = 0;
        $failed = 0;

        foreach ($dataRows as $index => $row) {
            if ($this->shouldSkipRowByStudentCode($row)) {
                continue;
            }

            $rowNumber = (int) $index + 2;
            $mapped = $this->mapper->mapRow($row, $rowNumber);

            if ($mapped['status'] === 'error') {
                $invalidRows++;
                $rows[] = $mapped;

                continue;
            }

            $conflict = $this->conflictValidator->validate($mapped['normalized_payload']);
            if (! ($conflict['ok'] ?? false)) {
                $invalidRows++;
                $rows[] = [
                    ...$mapped,
                    'status' => 'error',
                    'errors' => $conflict['errors'] ?? ['Conflict validation failed.'],
                ];

                continue;
            }

            $mode = (string) ($conflict['mode'] ?? 'create');
            $mapped['import_mode'] = $mode;
            $mapped['existing_action_log_id'] = $conflict['existing_action_log_id'] ?? null;
            $mapped['preserve_preview'] = $this->preservePreviewResolver->resolve(
                $mapped['normalized_payload'],
                $mapped['existing_action_log_id']
            );
            $validRows++;

            if (! $shouldExecute) {
                $rows[] = $mapped;

                continue;
            }

            try {
                DB::transaction(function () use ($mapped, $sharedUploadRecordId, $userId): void {
                    $payload = $mapped['normalized_payload'];
                    $actionLog = $this->resolveActionLogForExecution($mapped, $payload, $userId);
                    $this->applyImportMetadataUpdates($actionLog, $payload);
                    if ($sharedUploadRecordId !== null) {
                        $actionLog->attachments()->syncWithoutDetaching([$sharedUploadRecordId]);
                    }
                    $actionLog->update(['missing_documents' => $sharedUploadRecordId === null]);

                    if ($sharedUploadRecordId !== null
                        && $payload['action_type'] === 'ACADEMIC_DEFER'
                        && $actionLog->deferCase) {
                        $actionLog->deferCase->update(['upload_record_id' => $sharedUploadRecordId]);
                    }
                });

                $success++;
                $rows[] = [
                    ...$mapped,
                    'status' => 'success',
                    'errors' => [],
                ];
            } catch (\Throwable $e) {
                Log::error('Student action import row failed', [
                    'row_number' => $rowNumber,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
                $rows[] = [
                    ...$mapped,
                    'status' => 'error',
                    'errors' => ['Import failed on this row due to business validation error.'],
                ];
            }
        }

        return [
            'summary' => [
                'total' => $validRows + $invalidRows,
                'valid' => $validRows,
                'invalid' => $invalidRows,
                'success' => $shouldExecute ? $success : 0,
                'failed' => $shouldExecute ? ($failed + $invalidRows) : $invalidRows,
            ],
            'header' => $headerValidation,
            'rows' => $rows,
            'global_errors' => [],
        ];
    }

    private function getFirstSheet(UploadedFile $file): array
    {
        $sheets = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray
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

    private function shouldSkipRowByStudentCode(array $row): bool
    {
        return trim((string) ($row[0] ?? '')) === '';
    }

    private function emptyResponse(string $message): array
    {
        return [
            'summary' => [
                'total' => 0,
                'valid' => 0,
                'invalid' => 0,
                'success' => 0,
                'failed' => 0,
            ],
            'header' => ['ok' => false],
            'rows' => [],
            'global_errors' => [$message],
        ];
    }

    private function resolveActionLogForExecution(array $mapped, array $payload, int $userId): StudentActionLog
    {
        if (($mapped['import_mode'] ?? 'create') === 'append') {
            return StudentActionLog::query()->findOrFail((int) $mapped['existing_action_log_id']);
        }

        $payload['changed_by_user_id'] = $userId;

        return RecordStudentActionAction::run($payload);
    }

    private function applyImportMetadataUpdates(StudentActionLog $actionLog, array $payload): void
    {
        $actionLog->update([
            'reason' => $payload['reason'] ?? $actionLog->reason,
            'signed_at' => $payload['signed_at'] ?? null,
            'decision_number' => $payload['decision_number'] ?? null,
            'decision_signed_at' => $payload['decision_signed_at'] ?? null,
            'decision_signer' => $payload['decision_signer'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);
    }
}
