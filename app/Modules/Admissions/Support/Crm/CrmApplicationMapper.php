<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support\Crm;

use App\Modules\Admissions\Models\ApplicationAcademicScore;
use InvalidArgumentException;

/**
 * Pure raw-CRM-array → payload-array translation. No DB, no HTTP. Sync never
 * resolves CRM text to local codes here (D12/D16) — `crm_campus`, `crm_major`,
 * `scholarship`, `pathway_gateway`, `uu_dai_gc` are copied verbatim.
 *
 * A malformed `student_code` or unparseable `date_of_birth` throws — those
 * become part of an identity lookup / a hard-typed column, so failing the
 * record loudly beats silently propagating a bad value. Everything else
 * degrades to null rather than failing the record.
 */
final class CrmApplicationMapper
{
    private const SCHOOL_REPORT_SUBJECTS = [
        'toan', 'ly', 'hoa', 'sinh', 'tin_hoc', 'van', 'lich_su', 'dia_ly',
        'tieng_anh', 'giao_duc_cong_dan', 'giao_duc_quoc_phong', 'cong_nghe', 'kt_pl',
    ];

    private const NATIONAL_EXAM_SUBJECTS = [
        'thithpt_toan', 'thithpt_van', 'thithpt_option1', 'thithpt_option2',
    ];

    /**
     * @var list<array{crm_field: string, file_type_code: string, page_index: int}>
     */
    private const DOCUMENT_FIELDS = [
        ['crm_field' => 'file_student_photo', 'file_type_code' => 'student_photo', 'page_index' => 0],
        ['crm_field' => 'file_id_card_front', 'file_type_code' => 'id_card_front', 'page_index' => 0],
        ['crm_field' => 'file_id_card_back', 'file_type_code' => 'id_card_back', 'page_index' => 0],
        ['crm_field' => 'file_english_certificate', 'file_type_code' => 'english_certificate', 'page_index' => 0],
        ['crm_field' => 'file_english_certificare', 'file_type_code' => 'english_certificate', 'page_index' => 1],
        ['crm_field' => 'file_transcript', 'file_type_code' => 'transcript', 'page_index' => 0],
        ['crm_field' => 'file_transcript_1', 'file_type_code' => 'transcript', 'page_index' => 1],
        ['crm_field' => 'file_diploma', 'file_type_code' => 'diploma', 'page_index' => 0],
        ['crm_field' => 'file_other_achievements', 'file_type_code' => 'other_achievements', 'page_index' => 0],
        ['crm_field' => 'file_other_achievements_2', 'file_type_code' => 'other_achievements', 'page_index' => 1],
        ['crm_field' => 'ielts_certificate', 'file_type_code' => 'english_certificate', 'page_index' => 2],
        ['crm_field' => 'scholarship_cert_view_url', 'file_type_code' => 'scholarship_certificate', 'page_index' => 0],
    ];

    public function __construct(private readonly CrmDocumentUrlValidator $urlValidator) {}

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException naming only the offending field
     */
    public function map(array $raw): array
    {
        $studentCode = $this->mapStudentCode($raw);
        $birthDate = $this->mapBirthDate($raw);

        return [
            // No crm_admission_id key at all (not even null): a push-created
            // row matched by student_code must keep its crm_admission_id, or
            // the next push upsert can no longer find it and duplicates the
            // application. D1 leaves this path keyed on student_code only.
            'student_code' => $studentCode,
            'full_name' => $this->string($raw['name'] ?? null),
            'gender' => $this->mapGender($raw['gender'] ?? null),
            'ethnicity' => $this->string($raw['ethnicity'] ?? null),
            'birth_day' => $birthDate['day'],
            'birth_month' => $birthDate['month'],
            'birth_year' => $birthDate['year'],
            'national_id' => $this->string($raw['cccd'] ?? null),
            'phone' => $this->string($raw['phone'] ?? null),
            'email' => $this->string($raw['email'] ?? null),
            'address' => $this->string($raw['new_address'] ?? null) ?? $this->string($raw['address'] ?? null),
            'crm_campus' => $this->string($raw['campus'] ?? null),
            'crm_major' => $this->string($raw['major'] ?? null),
            'province' => $this->string($raw['province'] ?? null),
            'new_province' => $this->string($raw['new_province'] ?? null),
            'new_street' => $this->string($raw['new_street'] ?? null),
            'new_ward' => $this->string($raw['new_ward'] ?? null),
            'permanent_address' => $this->string($raw['permanent_address'] ?? null),
            'birth_place' => $this->string($raw['birth_place'] ?? null),
            'nationality' => $this->string($raw['nationality'] ?? null),
            'religion' => $this->string($raw['religion'] ?? null),
            'id_card_place_of_issue' => $this->string($raw['place_of_issue'] ?? null),
            'school' => $this->string($raw['school'] ?? null),
            'graduation_year' => $this->string($raw['graduation_year'] ?? null),
            'gpa' => $this->float($raw['gpa'] ?? null),
            'gpa_type' => $this->string($raw['gpa_type'] ?? null),
            'scholarship' => $this->string($raw['scholarship'] ?? null),
            'pathway_gateway' => $this->string($raw['pathway_gateway'] ?? null),
            'uu_dai_gc' => $this->string($raw['uu_dai_gc'] ?? null),
            'crm_paid_amount' => $this->float($raw['paid_amount'] ?? null),
            'english_test' => [
                'test_type' => $this->string($raw['english_certificate_type'] ?? null),
                'exam_date' => $this->string($raw['certificate_exam_date'] ?? null),
                'listening' => $this->float($raw['ielts_listening'] ?? null),
                'reading' => $this->float($raw['ielts_reading'] ?? null),
                'writing' => $this->float($raw['ielts_writing'] ?? null),
                'speaking' => $this->float($raw['ielts_speaking'] ?? null),
                'overall' => $this->float($raw['ielts_overall'] ?? null),
            ],
            'guardians' => $this->mapGuardians($raw),
            'documents' => $this->mapDocuments($raw, $studentCode),
            'academic_scores' => $this->mapAcademicScores($raw),
        ];
    }

    private function mapStudentCode(array $raw): string
    {
        $studentCode = $raw['student_code'] ?? null;

        if (! is_string($studentCode) || preg_match('/^[A-Za-z0-9_-]{1,20}$/', $studentCode) !== 1) {
            throw new InvalidArgumentException('student_code');
        }

        return $studentCode;
    }

    /** @return array{day: int|null, month: int|null, year: int|null} */
    private function mapBirthDate(array $raw): array
    {
        $value = $raw['date_of_birth'] ?? null;

        if ($value === null || $value === '') {
            return ['day' => null, 'month' => null, 'year' => null];
        }

        if (! is_string($value) || preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $matches) !== 1) {
            throw new InvalidArgumentException('date_of_birth');
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];

        if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('date_of_birth');
        }

        return ['day' => $day, 'month' => $month, 'year' => $year];
    }

    private function mapGender(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $lowered = strtolower(trim($value));

        return in_array($lowered, ['male', 'female', 'other'], true) ? $lowered : null;
    }

    /** @return list<array{relationship: string, full_name: string, phone: string|null, is_primary: bool}> */
    private function mapGuardians(array $raw): array
    {
        $guardians = [];

        $fatherName = $this->string($raw['father_name'] ?? null);
        if ($fatherName !== null) {
            $guardians[] = [
                'relationship' => 'father',
                'full_name' => $fatherName,
                'phone' => $this->string($raw['father_phone'] ?? null),
                'is_primary' => true,
            ];
        }

        $motherName = $this->string($raw['mother_name'] ?? null);
        if ($motherName !== null) {
            $guardians[] = [
                'relationship' => 'mother',
                'full_name' => $motherName,
                'phone' => $this->string($raw['mother_phone'] ?? null),
                'is_primary' => $fatherName === null,
            ];
        }

        return $guardians;
    }

    /** @return list<array{crm_file_id_suffix: string, file_type_code: string, page_index: int, link: string}> */
    private function mapDocuments(array $raw, string $studentCode): array
    {
        $documents = [];

        foreach (self::DOCUMENT_FIELDS as $field) {
            $url = $this->urlValidator->validate($raw[$field['crm_field']] ?? null);

            if ($url === null) {
                continue;
            }

            $documents[] = [
                'crm_file_id_suffix' => "{$field['file_type_code']}:{$field['page_index']}",
                'file_type_code' => $field['file_type_code'],
                'page_index' => $field['page_index'],
                'link' => $url,
            ];
        }

        return $documents;
    }

    /** @return list<array{subject_code: string, score: float, source: string}> */
    private function mapAcademicScores(array $raw): array
    {
        $scores = [];

        foreach (self::SCHOOL_REPORT_SUBJECTS as $subject) {
            $score = $this->float($raw[$subject] ?? null);
            if ($score !== null) {
                $scores[] = ['subject_code' => $subject, 'score' => $score, 'source' => ApplicationAcademicScore::SOURCE_SCHOOL_REPORT];
            }
        }

        foreach (self::NATIONAL_EXAM_SUBJECTS as $subject) {
            $score = $this->float($raw[$subject] ?? null);
            if ($score !== null) {
                $scores[] = ['subject_code' => $subject, 'score' => $score, 'source' => ApplicationAcademicScore::SOURCE_NATIONAL_EXAM];
            }
        }

        return $scores;
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function float(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value) || trim($value) === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
