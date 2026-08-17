<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\Student;

class BulkEgcPlacementRowMapper
{
    private const LEVEL_MAP = [
        'FOUNDATION' => 0,
        'EGC1' => 1,
        'EGC2' => 2,
        'EGC3' => 3,
        'EGC4' => 4,
        'EGC5' => 5,
    ];

    /** @return array{ok: bool, student_id_col?: int, level_col?: int, missing?: list<string>} */
    public function validateHeaders(array $headerRow): array
    {
        $normalized = array_map(fn ($v) => strtolower(trim((string) $v)), $headerRow);

        $studentIdCol = array_search('student id', $normalized, true);
        $levelCol = array_search('level', $normalized, true);

        $missing = [];
        if ($studentIdCol === false) {
            $missing[] = 'Student ID';
        }
        if ($levelCol === false) {
            $missing[] = 'Level';
        }

        if ($missing !== []) {
            return ['ok' => false, 'missing' => $missing];
        }

        return ['ok' => true, 'student_id_col' => (int) $studentIdCol, 'level_col' => (int) $levelCol];
    }

    /**
     * @return array{
     *     row_number: int,
     *     status: 'valid'|'skip',
     *     skip_reason: ?string,
     *     student: array{id: ?int, student_id: ?string, full_name: ?string},
     *     level_raw: ?string,
     *     level: ?int,
     *     normalized_payload: ?array{student_id: int, english_level: int},
     * }
     */
    public function mapRow(array $row, int $rowNumber, int $studentIdCol, int $levelCol, int $campusId): array
    {
        $studentIdRaw = trim((string) ($row[$studentIdCol] ?? ''));
        $levelRaw = trim((string) ($row[$levelCol] ?? ''));

        $student = $studentIdRaw !== ''
            ? Student::query()->where('student_id', $studentIdRaw)->where('campus_id', $campusId)->first()
            : null;

        $skipReason = $this->skipReason($studentIdRaw, $student, $levelRaw);
        $level = $skipReason === null ? $this->mapLevel($levelRaw) : null;

        return [
            'row_number' => $rowNumber,
            'status' => $skipReason === null ? 'valid' : 'skip',
            'skip_reason' => $skipReason,
            'student' => [
                'id' => $student?->id,
                'student_id' => $studentIdRaw !== '' ? $studentIdRaw : null,
                'full_name' => $student?->full_name,
            ],
            'level_raw' => $levelRaw !== '' ? $levelRaw : null,
            'level' => $level,
            'normalized_payload' => $skipReason === null
                ? ['student_id' => (int) $student->id, 'english_level' => $level]
                : null,
        ];
    }

    private function skipReason(string $studentIdRaw, ?Student $student, string $levelRaw): ?string
    {
        if ($studentIdRaw === '') {
            return 'student_id_missing';
        }
        if ($student === null) {
            return 'student_not_found';
        }
        if ($levelRaw === '') {
            return 'level_missing';
        }
        if (stripos($levelRaw, 'GCS') === 0) {
            return 'level_excluded_gcs';
        }
        if ($this->mapLevel($levelRaw) === null) {
            return 'level_unmapped';
        }

        return null;
    }

    private function mapLevel(string $levelRaw): ?int
    {
        $key = strtoupper(preg_replace('/[\s\x{00A0}]+/u', '', $levelRaw) ?? '');

        return self::LEVEL_MAP[$key] ?? null;
    }
}
