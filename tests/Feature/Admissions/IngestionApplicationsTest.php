<?php

declare(strict_types=1);

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentType;
use App\Models\StudentApplication;
use App\Models\User;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The ingestion throttle uses the cache store; clear it so per-test hit
    // counts do not leak across tests.
    Cache::flush();

    $service = User::factory()->create(['type' => UserType::SERVICE]);
    Sanctum::actingAs($service, [AdmissionsIngestion::ABILITY]);
    $this->service = $service;
});

function ingestUrl(): string
{
    return route('v1.admissions.applications.upsert');
}

/**
 * A minimal valid ingestion payload. Override any key per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ingestionPayload(array $overrides = []): array
{
    return array_merge([
        'crm_admission_id' => '743',
        'student_code' => 'AUS10743',
        'full_name' => 'Nguyen Van A',
        'gender' => 'male',
        'birth_day' => 1,
        'birth_month' => 2,
        'birth_year' => 2003,
        'national_id' => '0123456789',
        'phone' => '0900000000',
        'email' => 'applicant@example.test',
        'campus_code' => 'SAI',
        'intended_program' => 'IT',
        'intake' => 'Fall 2025',
        'is_international_applicant' => false,
        'guardians' => [
            [
                'full_name' => 'Tran Thi B',
                'relationship' => 'mother',
                'phone' => '0911111111',
                'email' => 'mother@example.test',
                'is_primary' => true,
            ],
        ],
        'documents' => [
            [
                'crm_file_id' => 'file-1',
                'file_type_code' => 'id_card_front',
                'file_type_name' => 'ID card (front)',
                'page_index' => 0,
                'original_name' => 'IMG_0923.jpeg',
                'link' => 'https://drive.example/19-kY2rN3rKXsUAq2lraoUKlUJhrHF8j7',
                'mime_type' => 'image/jpeg',
                'size' => 362636,
                'status' => 'active',
            ],
        ],
    ], $overrides);
}

it('creates an application with its guardians and documents', function () {
    $response = $this->postJson(ingestUrl(), ingestionPayload());

    $response->assertCreated()->assertJson(['success' => true]);

    $application = StudentApplication::where('crm_admission_id', '743')->firstOrFail();
    expect($application->status)->toBe(StudentApplication::STATUS_PENDING);
    expect($application->student_code)->toBe('AUS10743');
    expect($application->full_name)->toBe('Nguyen Van A');

    expect($application->guardians()->count())->toBe(1);
    $guardian = $application->guardians()->first();
    expect($guardian->full_name)->toBe('Tran Thi B');
    expect($guardian->is_primary)->toBeTrue();

    expect($application->documents()->count())->toBe(1);
    $document = $application->documents()->first();
    expect($document->crm_file_id)->toBe('file-1');
    expect($document->file_type_code)->toBe('id_card_front');
});

it('stores the CRM-issued student_code as provided and generates none', function () {
    $this->postJson(ingestUrl(), ingestionPayload(['student_code' => 'AUS99999']))
        ->assertCreated();

    expect(StudentApplication::where('crm_admission_id', '743')->value('student_code'))
        ->toBe('AUS99999');
});

it('upserts by crm_admission_id instead of duplicating the application', function () {
    $this->postJson(ingestUrl(), ingestionPayload())->assertCreated();
    $this->postJson(ingestUrl(), ingestionPayload(['full_name' => 'Nguyen Van A (corrected)']))
        ->assertOk();

    expect(StudentApplication::where('crm_admission_id', '743')->count())->toBe(1);
    expect(StudentApplication::where('crm_admission_id', '743')->value('full_name'))
        ->toBe('Nguyen Van A (corrected)');
});

it('upserts a document by crm_file_id instead of duplicating it', function () {
    $this->postJson(ingestUrl(), ingestionPayload())->assertCreated();

    $resend = ingestionPayload();
    $resend['documents'][0]['original_name'] = 'renamed.jpeg';
    $this->postJson(ingestUrl(), $resend)->assertOk();

    expect(ApplicationDocument::where('crm_file_id', 'file-1')->count())->toBe(1);
    expect(ApplicationDocument::where('crm_file_id', 'file-1')->value('original_name'))
        ->toBe('renamed.jpeg');
});

it('re-points a document to the latest admission without colliding on its global crm_file_id', function () {
    // crm_file_id is globally unique; the same file arriving under a different
    // admission must upsert (re-point) rather than hit the unique index. The two
    // applications carry distinct applicant identity (email/national_id/code are
    // independently unique on the core).
    $this->postJson(ingestUrl(), ingestionPayload([
        'crm_admission_id' => 'adm-1',
        'student_code' => 'CODE-1',
        'email' => 'one@example.test',
        'national_id' => '1111111111',
    ]))->assertCreated();

    $this->postJson(ingestUrl(), ingestionPayload([
        'crm_admission_id' => 'adm-2',
        'student_code' => 'CODE-2',
        'email' => 'two@example.test',
        'national_id' => '2222222222',
    ]))->assertCreated();

    expect(ApplicationDocument::where('crm_file_id', 'file-1')->count())->toBe(1);

    $document = ApplicationDocument::where('crm_file_id', 'file-1')->firstOrFail();
    $latest = StudentApplication::where('crm_admission_id', 'adm-2')->firstOrFail();
    expect($document->student_application_id)->toBe($latest->id);
});

it('overwrites fields while the application is pending', function () {
    $this->postJson(ingestUrl(), ingestionPayload())->assertCreated();

    $this->postJson(ingestUrl(), ingestionPayload([
        'phone' => '0988888888',
        'intended_program' => 'BUS',
    ]))->assertOk();

    $application = StudentApplication::where('crm_admission_id', '743')->firstOrFail();
    expect($application->phone)->toBe('0988888888');
    expect($application->intended_program)->toBe('BUS');
});

it('refuses to update an enrolled application with 409 and changes nothing', function () {
    $application = StudentApplication::factory()->create([
        'crm_admission_id' => '743',
        'status' => StudentApplication::STATUS_ENROLLED,
        'full_name' => 'Already Enrolled',
    ]);

    $this->postJson(ingestUrl(), ingestionPayload(['full_name' => 'Tampered']))
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'errors' => [['code' => 'CONFLICT']],
        ]);

    expect($application->fresh()->full_name)->toBe('Already Enrolled');
    expect($application->guardians()->count())->toBe(0);
});

it('refuses to update a rejected application with 409', function () {
    StudentApplication::factory()->rejected()->create(['crm_admission_id' => '743']);

    $this->postJson(ingestUrl(), ingestionPayload())
        ->assertStatus(409);
});

it('resolves a document file_type_name from the mirrored catalog when omitted', function () {
    ApplicationDocumentType::factory()->create([
        'code' => 'id_card_front',
        'name' => 'Catalog ID Card Front',
    ]);

    $payload = ingestionPayload();
    unset($payload['documents'][0]['file_type_name']);

    $this->postJson(ingestUrl(), $payload)->assertCreated();

    expect(ApplicationDocument::where('crm_file_id', 'file-1')->value('file_type_name'))
        ->toBe('Catalog ID Card Front');
});

it('keeps exactly one primary guardian when re-sending the guardian list', function () {
    $payload = ingestionPayload();
    $payload['guardians'] = [
        ['full_name' => 'First', 'relationship' => 'father', 'is_primary' => false],
        ['full_name' => 'Second', 'relationship' => 'mother', 'is_primary' => true],
    ];

    $this->postJson(ingestUrl(), $payload)->assertCreated();
    // Re-send identical: replace-set must not accumulate guardians.
    $this->postJson(ingestUrl(), $payload)->assertOk();

    $application = StudentApplication::where('crm_admission_id', '743')->firstOrFail();
    expect($application->guardians()->count())->toBe(2);
    expect($application->guardians()->where('is_primary', true)->count())->toBe(1);
    expect($application->primaryGuardian()->full_name)->toBe('Second');
});

it('returns validation errors in the ApiResponse envelope', function () {
    $this->postJson(ingestUrl(), ingestionPayload([
        'crm_admission_id' => '',
        'full_name' => '',
    ]))
        ->assertStatus(422)
        ->assertJson([
            'success' => false,
            'errors' => [
                ['code' => 'VALIDATION_ERROR'],
            ],
        ]);

    expect(StudentApplication::count())->toBe(0);
});

it('rejects a guardian with no full_name', function () {
    $payload = ingestionPayload();
    $payload['guardians'] = [['relationship' => 'mother']];

    $this->postJson(ingestUrl(), $payload)->assertStatus(422);
});

it('rejects a document with no crm_file_id', function () {
    $payload = ingestionPayload();
    unset($payload['documents'][0]['crm_file_id']);

    $this->postJson(ingestUrl(), $payload)->assertStatus(422);
});

it('maps an inline english test result onto the application', function () {
    $this->postJson(ingestUrl(), ingestionPayload([
        'english_test' => [
            'test_type' => 'IELTS',
            'exam_date' => '2025-01-15',
            'listening' => 7.0,
            'reading' => 6.5,
            'writing' => 6.0,
            'speaking' => 6.5,
            'overall' => 6.5,
        ],
    ]))->assertCreated();

    $application = StudentApplication::where('crm_admission_id', '743')->firstOrFail();
    expect($application->english_test_type)->toBe('IELTS');
    expect((float) $application->overall)->toBe(6.5);
});

it('exposes no approve/reject/revoke route on the ingestion surface', function () {
    foreach (['approve', 'reject', 'revoke'] as $action) {
        expect(fn () => route("v1.admissions.applications.$action"))->toThrow(Exception::class);
    }
});

it('does not let the payload set lifecycle status or audit fields', function () {
    $this->postJson(ingestUrl(), ingestionPayload([
        'status' => StudentApplication::STATUS_ENROLLED,
        'approved_by' => $this->service->id,
        'student_id' => 999,
    ]))->assertCreated();

    $application = StudentApplication::where('crm_admission_id', '743')->firstOrFail();
    expect($application->status)->toBe(StudentApplication::STATUS_PENDING);
    expect($application->approved_by)->toBeNull();
    expect($application->student_id)->toBeNull();
});
