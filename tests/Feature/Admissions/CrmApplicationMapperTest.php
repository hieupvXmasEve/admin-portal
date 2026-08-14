<?php

declare(strict_types=1);

use App\Modules\Admissions\Support\Crm\CrmApplicationMapper;
use App\Modules\Admissions\Support\Crm\CrmDocumentUrlValidator;

function mapper(): CrmApplicationMapper
{
    return new CrmApplicationMapper(new CrmDocumentUrlValidator);
}

function fullCrmRecord(array $overrides = []): array
{
    return array_merge([
        'student_code' => 'NE123456',
        'name' => 'Nguyen Van A',
        'gender' => 'Male',
        'ethnicity' => 'Kinh',
        'date_of_birth' => '05/03/2004',
        'cccd' => '079204001234',
        'phone' => '0900000001',
        'email' => 'ne@example.test',
        'new_address' => 'New addr',
        'address' => 'Old addr',
        'campus' => 'TP. Hồ Chí Minh',
        'major' => 'Trí tuệ nhân tạo',
        'province' => 'HCM',
        'new_province' => 'HCM2',
        'new_street' => 'Street',
        'new_ward' => 'Ward',
        'permanent_address' => 'Permanent addr',
        'birth_place' => 'HCM',
        'nationality' => 'Vietnamese',
        'religion' => 'None',
        'place_of_issue' => 'Cuc canh sat',
        'school' => 'THPT A',
        'graduation_year' => '2022',
        'gpa' => '8.5',
        'gpa_type' => '10',
        'scholarship' => 'HB50',
        'pathway_gateway' => 'PW1',
        'uu_dai_gc' => 'UD1',
        'paid_amount' => '1000000',
        'english_certificate_type' => 'IELTS',
        'certificate_exam_date' => '2023-01-01',
        'ielts_listening' => '7.5',
        'ielts_reading' => '7.0',
        'ielts_writing' => '6.5',
        'ielts_speaking' => '7.0',
        'ielts_overall' => '7.0',
        'father_name' => 'Nguyen Van B',
        'father_phone' => '0911111111',
        'mother_name' => 'Tran Thi C',
        'mother_phone' => '0922222222',
        'file_student_photo' => 'https://drive.example/photo',
        'file_id_card_front' => 'https://drive.example/idf',
        'file_id_card_back' => 'https://drive.example/idb',
        'file_english_certificate' => 'https://drive.example/cert0',
        'file_english_certificare' => 'https://drive.example/cert1',
        'file_transcript' => 'https://drive.example/t0',
        'file_transcript_1' => 'https://drive.example/t1',
        'file_diploma' => 'https://drive.example/diploma',
        'file_other_achievements' => 'https://drive.example/ach0',
        'file_other_achievements_2' => 'https://drive.example/ach1',
        'ielts_certificate' => 'https://drive.example/ielts',
        'scholarship_cert_view_url' => 'https://drive.example/sch',
        'file_id_card_photo' => 'https://drive.example/ignored',
        'registration_form' => 1,
        'toan' => '8.0', 'ly' => '7.5', 'hoa' => '7.0', 'sinh' => '8.0', 'tin_hoc' => '9.0',
        'van' => '7.0', 'lich_su' => '8.0', 'dia_ly' => '8.5', 'tieng_anh' => '9.0',
        'giao_duc_cong_dan' => '9.5', 'giao_duc_quoc_phong' => '9.0', 'cong_nghe' => '8.0', 'kt_pl' => '8.5',
        'thithpt_toan' => '8.25', 'thithpt_van' => '7.75', 'thithpt_option1' => '6.5', 'thithpt_option2' => '7.25',
    ], $overrides);
}

it('maps a full record with every field populated', function () {
    $payload = mapper()->map(fullCrmRecord());

    expect($payload['student_code'])->toBe('NE123456')
        ->and($payload['full_name'])->toBe('Nguyen Van A')
        ->and($payload['gender'])->toBe('male')
        ->and($payload['birth_day'])->toBe(5)
        ->and($payload['birth_month'])->toBe(3)
        ->and($payload['birth_year'])->toBe(2004)
        ->and($payload['address'])->toBe('New addr')
        ->and($payload['crm_campus'])->toBe('TP. Hồ Chí Minh')
        ->and($payload['crm_major'])->toBe('Trí tuệ nhân tạo')
        ->and($payload)->not->toHaveKey('crm_admission_id')
        ->and($payload['gpa'])->toBe(8.5)
        ->and($payload['crm_paid_amount'])->toBe(1000000.0)
        ->and($payload['registration_form'])->toBeTrue()
        ->and($payload['english_test']['listening'])->toBe(7.5)
        ->and($payload['guardians'])->toHaveCount(2)
        ->and($payload['documents'])->toHaveCount(12)
        ->and($payload['academic_scores'])->toHaveCount(17);

    expect(collect($payload['guardians'])->firstWhere('relationship', 'father'))
        ->toMatchArray(['full_name' => 'Nguyen Van B', 'is_primary' => true]);
    expect(collect($payload['guardians'])->firstWhere('relationship', 'mother'))
        ->toMatchArray(['full_name' => 'Tran Thi C', 'is_primary' => false]);
});

it('maps an all-null record without throwing, leaving optional fields null', function () {
    $payload = mapper()->map([
        'student_code' => 'NE000001',
    ]);

    expect($payload['full_name'])->toBeNull()
        ->and($payload['gender'])->toBeNull()
        ->and($payload['birth_day'])->toBeNull()
        ->and($payload['gpa'])->toBeNull()
        ->and($payload['guardians'])->toBe([])
        ->and($payload['documents'])->toBe([])
        ->and($payload['academic_scores'])->toBe([]);
});

it('maps a record with missing keys the same as an all-null record', function () {
    $payload = mapper()->map(['student_code' => 'NE000002']);

    expect($payload['crm_campus'])->toBeNull()
        ->and($payload['english_test']['overall'])->toBeNull();
});

it('throws on an unparseable date_of_birth naming only the field', function () {
    try {
        mapper()->map(fullCrmRecord(['date_of_birth' => 'not-a-date']));
        expect(false)->toBeTrue('expected an exception');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toBe('date_of_birth');
    }
});

it('drops a non-numeric score to null instead of failing the record', function () {
    $payload = mapper()->map(fullCrmRecord(['toan' => 'not-a-number']));

    expect(collect($payload['academic_scores'])->firstWhere('subject_code', 'toan'))->toBeNull();
});

it('maps an unknown gender value to null', function () {
    $payload = mapper()->map(fullCrmRecord(['gender' => 'unspecified']));

    expect($payload['gender'])->toBeNull();
});

it('throws on a malformed student_code naming only the field', function () {
    try {
        mapper()->map(fullCrmRecord(['student_code' => 'bad code!']));
        expect(false)->toBeTrue('expected an exception');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toBe('student_code');
    }
});

it('rejects a javascript: document URL', function () {
    $payload = mapper()->map(fullCrmRecord(['file_diploma' => 'javascript:alert(1)']));

    expect(collect($payload['documents'])->firstWhere('file_type_code', 'diploma'))->toBeNull();
});

it('rejects a data: document URL', function () {
    $payload = mapper()->map(fullCrmRecord(['file_diploma' => 'data:text/html,<script>alert(1)</script>']));

    expect(collect($payload['documents'])->firstWhere('file_type_code', 'diploma'))->toBeNull();
});

it('rejects a plain http: document URL (https only)', function () {
    $payload = mapper()->map(fullCrmRecord(['file_diploma' => 'http://insecure.example/doc']));

    expect(collect($payload['documents'])->firstWhere('file_type_code', 'diploma'))->toBeNull();
});

it('ignores file_id_card_photo as a document (no catalog code for it)', function () {
    $payload = mapper()->map(fullCrmRecord());

    $codes = collect($payload['documents'])->pluck('file_type_code');
    expect($codes)->not->toContain('id_card_photo')
        ->and($codes)->not->toContain('registration_form');
});

it('maps registration_form to a boolean, not a document', function () {
    expect(mapper()->map(fullCrmRecord(['registration_form' => 1]))['registration_form'])->toBeTrue()
        ->and(mapper()->map(fullCrmRecord(['registration_form' => 0]))['registration_form'])->toBeFalse()
        ->and(mapper()->map(fullCrmRecord(['registration_form' => null]))['registration_form'])->toBeNull();
});
