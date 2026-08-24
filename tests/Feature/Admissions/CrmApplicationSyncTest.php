<?php

declare(strict_types=1);

use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Models\ApplicationAcademicScore;
use App\Modules\Admissions\Models\CrmValueMapping;
use App\Modules\Admissions\Services\CrmApplicationSyncService;
use App\Modules\Admissions\Support\Crm\CrmIntegrationSettings;
use App\Modules\Upload\Models\ApplicationDocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.crm.login_url' => 'https://crm.test/api/login',
        'services.crm.data_url' => 'https://crm.test/api/ne',
        'services.crm.username' => 'crm-user',
        'services.crm.password' => 'secret',
        'services.crm.timeout' => 5,
    ]);
    // Login is explicit and one-time (Phase 3 addendum): a sync run reuses a
    // stored token rather than logging in implicitly, so tests simulate an
    // operator who already logged in once.
    app(CrmIntegrationSettings::class)->saveToken('tok', 'Bearer');
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok', 'token_type' => 'Bearer']], 200),
    ]);
});

function fakeNeResponse(array $records): void
{
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok', 'token_type' => 'Bearer']], 200),
        'https://crm.test/api/ne' => Http::response(['data' => $records], 200),
    ]);
}

function neRecord(array $overrides = []): array
{
    return array_merge([
        'student_code' => 'NES0000001',
        'name' => 'Sync Applicant',
        'gender' => 'female',
        'date_of_birth' => '10/10/2003',
        'cccd' => '123456789012',
        'phone' => '0900000099',
        'email' => 'sync-applicant@example.test',
        'campus' => 'Unmapped Campus',
        'major' => 'Unmapped Major',
        'gpa' => '8.0',
        'paid_amount' => '500000',
        'father_name' => 'Father Name',
        'father_phone' => '0911000001',
        'mother_name' => 'Mother Name',
        'mother_phone' => '0922000002',
        'file_diploma' => 'https://drive.example/diploma',
        'file_transcript' => 'https://drive.example/t0',
        'toan' => '8.5',
        'thithpt_toan' => '7.5',
    ], $overrides);
}

it('creates a new application from a CRM record', function () {
    fakeNeResponse([neRecord()]);

    $result = app(CrmApplicationSyncService::class)->run(false, null);

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(0);

    $application = StudentApplication::query()->where('student_code', 'NES0000001')->first();
    expect($application)->not->toBeNull()
        ->and($application->crm_campus)->toBe('Unmapped Campus')
        ->and($application->crm_major)->toBe('Unmapped Major')
        ->and((float) $application->gpa)->toBe(8.0)
        ->and((float) $application->crm_paid_amount)->toBe(500000.0)
        ->and($application->last_synced_at)->not->toBeNull()
        ->and($application->status)->toBe(StudentApplication::STATUS_PENDING);
});

it('updates an existing pending application matched by student_code', function () {
    $application = StudentApplication::factory()->pending()->create(['student_code' => 'NES0000001', 'full_name' => 'Old Name']);

    fakeNeResponse([neRecord(['name' => 'New Name'])]);

    $result = app(CrmApplicationSyncService::class)->run(false, null);

    expect($result['updated'])->toBe(1)
        ->and($application->fresh()->full_name)->toBe('New Name');
});

it('does not create duplicates on two consecutive runs', function () {
    fakeNeResponse([neRecord()]);
    $service = app(CrmApplicationSyncService::class);

    $service->run(false, null);
    fakeNeResponse([neRecord()]);
    $service->run(false, null);

    expect(StudentApplication::query()->where('student_code', 'NES0000001')->count())->toBe(1);
});

it('skips a non-pending application and logs it, leaving local edits untouched', function () {
    $application = StudentApplication::factory()->create([
        'student_code' => 'NES0000001',
        'status' => StudentApplication::STATUS_ENROLLED,
        'full_name' => 'Locked Name',
    ]);

    fakeNeResponse([neRecord(['name' => 'Should Not Apply'])]);

    $result = app(CrmApplicationSyncService::class)->run(false, null);

    expect($result['skipped'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and($application->fresh()->full_name)->toBe('Locked Name');
});

it('isolates a bad record so the rest of the batch still lands, and reports non-zero failures', function () {
    fakeNeResponse([
        neRecord(['student_code' => 'NES0000001']),
        neRecord(['student_code' => 'NES0000002', 'date_of_birth' => 'garbage']),
    ]);

    $result = app(CrmApplicationSyncService::class)->run(false, null);

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(1)
        ->and($result['failures'][0]['field'])->toBe('date_of_birth')
        ->and(StudentApplication::query()->where('student_code', 'NES0000001')->exists())->toBeTrue()
        ->and(StudentApplication::query()->where('student_code', 'NES0000002')->exists())->toBeFalse();
});

it('a pre-existing manual application with null crm_admission_id is untouched by a full sync run', function () {
    $manual = StudentApplication::factory()->pending()->create([
        'student_code' => 'MANUAL0001',
        'full_name' => 'Manual Applicant',
        'crm_admission_id' => null,
    ]);

    fakeNeResponse([neRecord(['student_code' => 'NES0000001'])]);
    app(CrmApplicationSyncService::class)->run(false, null);

    expect($manual->fresh()->full_name)->toBe('Manual Applicant')
        ->and($manual->fresh()->student_code)->toBe('MANUAL0001');
});

it('preserves crm_admission_id on a push-created row matched by student_code (H1 regression)', function () {
    $pushCreated = StudentApplication::factory()->pending()->create([
        'student_code' => 'NES0000001',
        'crm_admission_id' => 'push-743',
    ]);

    fakeNeResponse([neRecord(['student_code' => 'NES0000001'])]);
    app(CrmApplicationSyncService::class)->run(false, null);

    expect($pushCreated->fresh()->crm_admission_id)->toBe('push-743');
});

it('does not demote the existing primary guardian when the CRM record has no father or mother', function () {
    $application = StudentApplication::factory()->pending()->create(['student_code' => 'NES0000001']);
    $staffGuardian = ApplicationGuardian::query()->create([
        'student_application_id' => $application->id,
        'full_name' => 'Staff Added Guardian',
        'relationship' => 'guardian',
        'is_primary' => true,
    ]);

    fakeNeResponse([neRecord(['father_name' => null, 'father_phone' => null, 'mother_name' => null, 'mother_phone' => null])]);
    app(CrmApplicationSyncService::class)->run(false, null);

    expect($staffGuardian->fresh()->is_primary)->toBeTrue();
});

it('persists transcript, diploma document rows and per-subject scores', function () {
    fakeNeResponse([neRecord()]);
    app(CrmApplicationSyncService::class)->run(false, null);

    $application = StudentApplication::query()->where('student_code', 'NES0000001')->first();

    expect($application->documents()->where('file_type_code', 'diploma')->exists())->toBeTrue()
        ->and($application->documents()->where('file_type_code', 'transcript')->exists())->toBeTrue()
        ->and((float) ApplicationAcademicScore::query()->where('student_application_id', $application->id)->where('subject_code', 'toan')->value('score'))->toBe(8.5)
        ->and((float) ApplicationAcademicScore::query()->where('student_application_id', $application->id)->where('subject_code', 'thithpt_toan')->value('score'))->toBe(7.5);
});

it('rejects a non-https document URL so it never reaches the database', function () {
    fakeNeResponse([neRecord(['file_diploma' => 'javascript:alert(1)'])]);
    app(CrmApplicationSyncService::class)->run(false, null);

    $application = StudentApplication::query()->where('student_code', 'NES0000001')->first();
    expect($application->documents()->where('file_type_code', 'diploma')->exists())->toBeFalse();
});

it('removes a stale ne:-prefixed document when its CRM URL goes null on a second run', function () {
    $call = 0;
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok']], 200),
        'https://crm.test/api/ne' => function () use (&$call) {
            $call++;

            return Http::response(['data' => [neRecord($call === 1 ? [] : ['file_diploma' => null])]], 200);
        },
    ]);
    $service = app(CrmApplicationSyncService::class);
    $service->run(false, null);
    $service->run(false, null);

    $application = StudentApplication::query()->where('student_code', 'NES0000001')->first();
    expect($application->documents()->where('file_type_code', 'diploma')->exists())->toBeFalse()
        ->and($application->documents()->where('file_type_code', 'transcript')->exists())->toBeTrue();
});

it('treats an all-documents-null second run as a no-op, not a delete-all (ADR-0050)', function () {
    // Distinct from the test above: here EVERY file_* field goes null on the
    // second run, so the mapper produces a genuinely empty documents array —
    // the exact case that used to wipe every previously-synced ne:-prefixed
    // document (no ambiguity signal exists to tell "applicant now has zero
    // documents" apart from "partial/glitched response").
    $call = 0;
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok']], 200),
        'https://crm.test/api/ne' => function () use (&$call) {
            $call++;

            return Http::response(['data' => [neRecord($call === 1 ? [] : ['file_diploma' => null, 'file_transcript' => null])]], 200);
        },
    ]);
    $service = app(CrmApplicationSyncService::class);
    $service->run(false, null);
    $service->run(false, null);

    $application = StudentApplication::query()->where('student_code', 'NES0000001')->first();
    expect($application->documents()->where('file_type_code', 'diploma')->exists())->toBeTrue()
        ->and($application->documents()->where('file_type_code', 'transcript')->exists())->toBeTrue();
});

it('flips the primary guardian from father to mother on a second run without hitting the one-primary index', function () {
    $call = 0;
    Http::fake([
        'https://crm.test/api/login' => Http::response(['status' => 'success', 'data' => ['token' => 'tok']], 200),
        'https://crm.test/api/ne' => function () use (&$call) {
            $call++;

            return Http::response(['data' => [neRecord($call === 1 ? [] : ['father_name' => null, 'father_phone' => null])]], 200);
        },
    ]);
    $service = app(CrmApplicationSyncService::class);
    $service->run(false, null);

    $application = StudentApplication::query()->where('student_code', 'NES0000001')->first();
    expect($application->guardians()->where('relationship', 'father')->where('is_primary', true)->exists())->toBeTrue();

    $service->run(false, null);

    $application->refresh();
    expect($application->guardians()->where('relationship', 'mother')->where('is_primary', true)->exists())->toBeTrue()
        ->and($application->guardians()->where('is_primary', true)->count())->toBe(1);
});

it('leaves a staff-added guardian on a different relationship untouched by a sync', function () {
    $application = StudentApplication::factory()->pending()->create(['student_code' => 'NES0000001']);
    ApplicationGuardian::query()->create([
        'student_application_id' => $application->id,
        'full_name' => 'Staff Added',
        'relationship' => 'guardian',
        'is_primary' => false,
    ]);

    fakeNeResponse([neRecord()]);
    app(CrmApplicationSyncService::class)->run(false, null);

    expect(ApplicationGuardian::query()->where('student_application_id', $application->id)->where('relationship', 'guardian')->exists())->toBeTrue();
});

it('accepts a record with blank or duplicated email', function () {
    StudentApplication::factory()->pending()->create(['student_code' => 'EXISTING001', 'email' => 'shared@example.test']);

    fakeNeResponse([neRecord(['student_code' => 'NES0000001', 'email' => 'shared@example.test'])]);
    $result = app(CrmApplicationSyncService::class)->run(false, null);

    expect($result['failed'])->toBe(0)
        ->and(StudentApplication::query()->where('student_code', 'NES0000001')->exists())->toBeTrue();
});

it('--dry-run performs zero writes', function () {
    fakeNeResponse([neRecord()]);

    $result = app(CrmApplicationSyncService::class)->run(true, null);

    expect($result['created'])->toBe(1)
        ->and(StudentApplication::query()->where('student_code', 'NES0000001')->exists())->toBeFalse();
});

it('artisan command exits non-zero when any record failed', function () {
    fakeNeResponse([neRecord(['student_code' => 'NES0000001', 'date_of_birth' => 'garbage'])]);

    $this->artisan('admissions:sync-crm-ne')->assertExitCode(1);
});

it('artisan command exits zero on a fully successful run', function () {
    fakeNeResponse([neRecord()]);

    $this->artisan('admissions:sync-crm-ne')->assertExitCode(0);
});

it('seeds id_card_back and scholarship_certificate document types idempotently', function () {
    fakeNeResponse([neRecord()]);
    $service = app(CrmApplicationSyncService::class);
    $service->run(false, null);
    fakeNeResponse([neRecord()]);
    $service->run(false, null);

    expect(ApplicationDocumentType::query()->where('code', 'id_card_back')->count())->toBe(1)
        ->and(ApplicationDocumentType::query()->where('code', 'scholarship_certificate')->count())->toBe(1);
});

it('resolves campus_code on a newly synced application within the same run', function () {
    // Mapping already configured; the record is inserted DURING this run.
    CrmValueMapping::create(['kind' => CrmValueMapping::KIND_CAMPUS, 'crm_value' => 'Hà Nội', 'local_code' => 'HN']);
    fakeNeResponse([neRecord(['student_code' => 'NEHN000001', 'campus' => 'Hà Nội'])]);

    app(CrmApplicationSyncService::class)->run(false, null);

    // Timing-gap fix: resolve runs AFTER the upsert, so the row just inserted
    // is mapped this run instead of waiting for the next sync.
    $application = StudentApplication::where('student_code', 'NEHN000001')->firstOrFail();
    expect($application->campus_code)->toBe('HN');
});
