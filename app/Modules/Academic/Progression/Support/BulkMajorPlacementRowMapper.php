<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

use App\Models\IeltsCertificate;
use App\Models\Student;
use App\Models\StudentApplication;

class BulkMajorPlacementRowMapper
{
    // IELTS band scale max — guards against a wrong-scale value (e.g. a TOEFL
    // score) slipping through under english_test_type='IELTS' by mistake.
    private const MAX_PLAUSIBLE_IELTS_SCORE = 9.0;

    /** @return array{ok: bool, student_id_col?: int, missing?: list<string>} */
    public function validateHeaders(array $headerRow): array
    {
        $normalized = array_map(fn ($v) => strtolower(trim((string) $v)), $headerRow);

        $studentIdCol = array_search('student id', $normalized, true);
        if ($studentIdCol === false) {
            return ['ok' => false, 'missing' => ['Student ID']];
        }

        return ['ok' => true, 'student_id_col' => (int) $studentIdCol];
    }

    /**
     * @return array{
     *     row_number: int,
     *     status: 'valid'|'skip',
     *     skip_reason: ?string,
     *     student: array{id: ?int, student_id: ?string, full_name: ?string},
     *     overall_score: ?float,
     *     application_exam_date: ?string,
     *     normalized_payload: ?array{student_id: int, ielts_score: float, issue_date: ?string},
     * }
     */
    public function mapRow(array $row, int $rowNumber, int $studentIdCol, int $campusId): array
    {
        $studentIdRaw = trim((string) ($row[$studentIdCol] ?? ''));

        $student = $studentIdRaw !== ''
            ? Student::query()->where('student_id', $studentIdRaw)->where('campus_id', $campusId)->first()
            : null;

        $application = $student !== null
            ? StudentApplication::query()
                ->where('student_id', $student->id)
                ->where('english_test_type', 'IELTS')
                ->whereNotNull('overall')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first()
            : null;

        $overallScore = $application !== null ? (float) $application->overall : null;
        $skipReason = $this->skipReason($studentIdRaw, $student, $application, $overallScore);

        return [
            'row_number' => $rowNumber,
            'status' => $skipReason === null ? 'valid' : 'skip',
            'skip_reason' => $skipReason,
            'student' => [
                'id' => $student?->id,
                'student_id' => $studentIdRaw !== '' ? $studentIdRaw : null,
                'full_name' => $student?->full_name,
            ],
            'overall_score' => $overallScore,
            'application_exam_date' => $application?->exam_date?->toDateString(),
            'normalized_payload' => $skipReason === null
                ? [
                    'student_id' => (int) $student->id,
                    'ielts_score' => $overallScore,
                    'issue_date' => $application?->exam_date?->toDateString(),
                ]
                : null,
        ];
    }

    private function skipReason(string $studentIdRaw, ?Student $student, ?StudentApplication $application, ?float $overallScore): ?string
    {
        if ($studentIdRaw === '') {
            return 'student_id_missing';
        }
        if ($student === null) {
            return 'student_not_found';
        }
        if ($application === null) {
            return 'application_score_missing';
        }
        if ($overallScore > self::MAX_PLAUSIBLE_IELTS_SCORE) {
            return 'score_out_of_range';
        }
        if ($overallScore < IeltsCertificate::SCORE_THRESHOLD_INTAKE_COURSE) {
            return 'below_threshold_excluded';
        }

        return null;
    }
}
