<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Support;

class StudentActionExcelRowMapper
{
    public const SUPPORTED_ACTION_TYPES = [
        'ACADEMIC_DEFER',
        'ACADEMIC_RESUME',
        'ACADEMIC_DROPOUT',
        'CAMPUS_TRANSFER',
    ];

    public const EXPECTED_HEADERS = [
        'student_code', 'action_type', 'reason', 'from_semester_code', 'return_semester_code',
        'defer_preserve_tuition', 'egc_defer_from_block_number', 'dropout_semester_code', 'from_campus_code', 'to_campus_code',
        'effective_at', 'signed_at', 'decision_number', 'decision_signed_at', 'decision_signer', 'notes',
    ];

    public const LEGACY_EXPECTED_HEADERS = [
        'student_code', 'action_type', 'reason', 'from_semester_code', 'return_semester_code',
        'defer_preserve_tuition', 'dropout_semester_code', 'from_campus_code', 'to_campus_code',
        'effective_at', 'signed_at', 'decision_number', 'decision_signed_at', 'decision_signer', 'notes',
    ];

    public function __construct(private readonly StudentActionExcelReferenceResolver $resolver) {}

    public function validateHeaders(array $headerRow): array
    {
        $normalized = array_values(array_map(fn ($v) => trim((string) $v), $headerRow));
        while (! empty($normalized) && end($normalized) === '') {
            array_pop($normalized);
        }

        foreach ([
            'current' => self::EXPECTED_HEADERS,
            'legacy' => self::LEGACY_EXPECTED_HEADERS,
        ] as $format => $expectedHeaders) {
            $expectedCount = count($expectedHeaders);
            $received = array_slice($normalized, 0, $expectedCount);
            $extraColumns = array_slice($normalized, $expectedCount);
            $hasExtraNamedColumn = array_filter($extraColumns, fn (string $value) => $value !== '') !== [];

            if ($received === $expectedHeaders && ! $hasExtraNamedColumn) {
                return ['ok' => true, 'format' => $format];
            }
        }

        return [
            'ok' => false,
            'expected' => self::EXPECTED_HEADERS,
            'legacy_expected' => self::LEGACY_EXPECTED_HEADERS,
            'received' => array_slice($normalized, 0, count(self::EXPECTED_HEADERS)),
        ];
    }

    public function mapRow(array $row, int $rowNumber, string $headerFormat = 'current'): array
    {
        $d = $this->toAssoc($row, $headerFormat);
        $errors = [];

        $studentCode = $this->str($d['student_code']);
        $actionType = strtoupper($this->str($d['action_type']));
        $reason = $this->str($d['reason']);

        if ($studentCode === '') {
            $errors[] = 'student_code is required.';
        }
        if ($actionType === '') {
            $errors[] = 'action_type is required.';
        } elseif (! in_array($actionType, self::SUPPORTED_ACTION_TYPES, true)) {
            $errors[] = 'action_type is invalid. ADMISSION_DEFERRAL is not supported in import.';
        }
        if ($reason === '') {
            $errors[] = 'reason is required.';
        }

        $student = $studentCode !== '' ? $this->resolver->studentByCode($studentCode) : null;
        if ($studentCode !== '' && ! $student) {
            $errors[] = "student_code '{$studentCode}' not found.";
        }

        $fromSemester = $this->semester($d['from_semester_code'], 'from_semester_code', $errors);
        $returnSemester = $this->semester($d['return_semester_code'], 'return_semester_code', $errors);
        $dropoutSemester = $this->semester($d['dropout_semester_code'], 'dropout_semester_code', $errors);
        $fromCampus = $this->campus($d['from_campus_code'], 'from_campus_code', $errors);
        $toCampus = $this->campus($d['to_campus_code'], 'to_campus_code', $errors);

        $effectiveAt = $this->dateTime($d['effective_at'], 'effective_at', $errors);
        $signedAt = $this->date($d['signed_at'], 'signed_at', $errors);
        $decisionSignedAt = $this->date($d['decision_signed_at'], 'decision_signed_at', $errors);

        $deferFlag = strtolower($this->str($d['defer_preserve_tuition']));
        if ($deferFlag !== '' && ! in_array($deferFlag, ['yes', 'no'], true)) {
            $errors[] = 'defer_preserve_tuition must be yes or no.';
        }

        $egcDeferBlockValue = $this->nullable($d['egc_defer_from_block_number'] ?? null);
        $egcDeferBlockNumber = null;
        if ($egcDeferBlockValue !== null) {
            if (! in_array($egcDeferBlockValue, ['1', '2'], true)) {
                $errors[] = 'egc_defer_from_block_number must be 1 or 2.';
            } else {
                $egcDeferBlockNumber = (int) $egcDeferBlockValue;
            }
        }

        $payload = [
            'student_id' => $student?->id,
            'action_type' => $actionType,
            'reason' => $reason,
            'notes' => $this->nullable($d['notes']),
            'signed_at' => $signedAt,
            'decision_number' => $this->nullable($d['decision_number']),
            'decision_signed_at' => $decisionSignedAt,
            'decision_signer' => $this->nullable($d['decision_signer']),
            'missing_documents' => false,
        ];

        if ($actionType === 'ACADEMIC_DEFER') {
            if (! $fromSemester) {
                $errors[] = 'from_semester_code is required for ACADEMIC_DEFER.';
            }
            if (! $returnSemester) {
                $errors[] = 'return_semester_code is required for ACADEMIC_DEFER.';
            }
            if ($deferFlag === '') {
                $errors[] = 'defer_preserve_tuition is required for ACADEMIC_DEFER.';
            }
            if ($student?->status === 'intake_pre_uni_gc') {
                $payload['egc_defer_from_block_number'] = $egcDeferBlockNumber ?? 1;
            } elseif ($egcDeferBlockNumber !== null) {
                $errors[] = 'egc_defer_from_block_number is only available for EGC defer students.';
            }
            if ($fromSemester && $returnSemester && $returnSemester->id < $fromSemester->id) {
                $errors[] = 'return_semester_code must be after from_semester_code.';
            }
            $payload += [
                'from_semester_id' => $fromSemester?->id,
                'return_semester_id' => $returnSemester?->id,
                'defer_scope_type' => 'FULL',
                'defer_fee_policy' => $deferFlag === 'yes' ? 'PRESERVE' : 'FORFEIT',
            ];
        }

        if ($actionType === 'ACADEMIC_RESUME') {
            if (! $returnSemester) {
                $errors[] = 'return_semester_code is required for ACADEMIC_RESUME.';
            }
            $payload['return_semester_id'] = $returnSemester?->id;
        }

        if ($actionType === 'ACADEMIC_DROPOUT') {
            if (! $dropoutSemester) {
                $errors[] = 'dropout_semester_code is required for ACADEMIC_DROPOUT.';
            }
            $payload['dropout_semester_id'] = $dropoutSemester?->id;
        }

        if ($actionType === 'CAMPUS_TRANSFER') {
            if (! $fromCampus) {
                $errors[] = 'from_campus_code is required for CAMPUS_TRANSFER.';
            }
            if (! $toCampus) {
                $errors[] = 'to_campus_code is required for CAMPUS_TRANSFER.';
            }
            if ($fromCampus && $toCampus && $fromCampus->id === $toCampus->id) {
                $errors[] = 'to_campus_code must be different from from_campus_code.';
            }
            if (! $effectiveAt) {
                $errors[] = 'effective_at is required for CAMPUS_TRANSFER.';
            }
            $effectiveSemester = $effectiveAt ? $this->resolver->semesterByDateTime($effectiveAt) : null;
            if ($effectiveAt && ! $effectiveSemester) {
                $errors[] = 'effective_at does not fall into any semester.';
            }
            $payload += [
                'from_campus_id' => $fromCampus?->id,
                'to_campus_id' => $toCampus?->id,
                'effective_at' => $effectiveAt,
                'effective_semester_id' => $effectiveSemester?->id,
            ];
        }

        return [
            'row_number' => $rowNumber,
            'status' => empty($errors) ? 'valid' : 'error',
            'errors' => $errors,
            'student' => [
                'id' => $student?->id,
                'student_code' => $studentCode !== '' ? $studentCode : null,
                'full_name' => $student?->full_name,
            ],
            'action_type' => $actionType !== '' ? $actionType : null,
            'reason' => $reason !== '' ? $reason : null,
            'decision_info' => [
                'decision_number' => $payload['decision_number'] ?? null,
                'decision_signed_at' => $payload['decision_signed_at'] ?? null,
                'decision_signer' => $payload['decision_signer'] ?? null,
            ],
            'semester_info' => [
                'from_semester_id' => $fromSemester?->id,
                'from_semester_code' => $this->nullable($d['from_semester_code']),
                'return_semester_id' => $returnSemester?->id,
                'return_semester_code' => $this->nullable($d['return_semester_code']),
                'egc_defer_from_block_number' => $payload['egc_defer_from_block_number'] ?? null,
                'dropout_semester_id' => $dropoutSemester?->id,
                'dropout_semester_code' => $this->nullable($d['dropout_semester_code']),
                'effective_semester_id' => $payload['effective_semester_id'] ?? null,
                'effective_at' => $effectiveAt,
            ],
            'normalized_payload' => empty($errors) ? $payload : null,
        ];
    }

    private function toAssoc(array $row, string $headerFormat): array
    {
        $headers = $headerFormat === 'legacy'
            ? self::LEGACY_EXPECTED_HEADERS
            : self::EXPECTED_HEADERS;
        $assoc = [];
        foreach ($headers as $idx => $h) {
            $assoc[$h] = $row[$idx] ?? null;
        }

        return $assoc;
    }

    private function str(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    private function nullable(mixed $v): ?string
    {
        $s = $this->str($v);

        return $s === '' ? null : $s;
    }

    private function semester(mixed $value, string $column, array &$errors)
    {
        $code = $this->str($value);
        if ($code === '') {
            return null;
        }
        $model = $this->resolver->semesterByCode($code);
        if (! $model) {
            $errors[] = "{$column} '{$code}' not found.";
        }

        return $model;
    }

    private function campus(mixed $value, string $column, array &$errors)
    {
        $code = $this->str($value);
        if ($code === '') {
            return null;
        }
        $model = $this->resolver->campusByCode($code);
        if (! $model) {
            $errors[] = "{$column} '{$code}' not found.";
        }

        return $model;
    }

    private function date(mixed $value, string $column, array &$errors): ?string
    {
        try {
            return $this->resolver->normalizeDate($value);
        } catch (\Throwable) {
            $errors[] = "{$column} has invalid date format.";

            return null;
        }
    }

    private function dateTime(mixed $value, string $column, array &$errors): ?string
    {
        try {
            return $this->resolver->normalizeDateTime($value);
        } catch (\Throwable) {
            $errors[] = "{$column} has invalid datetime format.";

            return null;
        }
    }
}
