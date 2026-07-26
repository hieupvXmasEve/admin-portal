<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

// Route uses ['web','auth'] middleware — CSRF verification is active.
// Seed a known token so tests can attach a matching X-CSRF-TOKEN header.
const NAC_ENDPOINT_TEST_CSRF = 'nac-endpoint-test-csrf';

beforeEach(function () {
    $campus = Campus::factory()->create();
    app()->singleton('campus', fn () => $campus);

    session([
        '_token' => NAC_ENDPOINT_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function nacApiUser(array $permissions = ['view_finance_operations_generate_charges']): User
{
    $user = User::factory()->create();
    $mock = Mockery::mock(CampusPermissionReader::class);
    $mock->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
    app()->singleton(CampusPermissionReader::class, fn () => $mock);

    return $user;
}

function csvUpload(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('students.csv', $content);
}

function makeNacApiStudent(Campus $campus, string $code): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

// ---------------------------------------------------------------------------
// Auth: unauthenticated returns 401 JSON (ApiExceptionHandler intercepts AuthenticationException)
// ---------------------------------------------------------------------------

it('returns 401 JSON for unauthenticated requests', function () {
    $semester = Semester::factory()->active()->create();

    $this->withHeaders(['X-CSRF-TOKEN' => NAC_ENDPOINT_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            [
                'fee_type' => 'bhyt',
                'semester_id' => $semester->id,
                'amount' => 500000,
                'due_date' => now()->addDays(30)->toDateString(),
                'csv_file' => csvUpload("student_code\nSE100001\n"),
            ],
        )->assertStatus(401)
        ->assertJson(['success' => false]);
});

// ---------------------------------------------------------------------------
// Permission gate (F2): 403 when permission absent
// ---------------------------------------------------------------------------

it('returns 403 when the user lacks the required permission', function () {
    $semester = Semester::factory()->active()->create();
    $user = nacApiUser([]);

    $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_ENDPOINT_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            [
                'fee_type' => 'bhyt',
                'semester_id' => $semester->id,
                'amount' => 500000,
                'due_date' => now()->addDays(30)->toDateString(),
                'csv_file' => csvUpload("student_code\nSE100001\n"),
            ],
        )
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Happy path: ApiResponse shape (F4) + created row persisted in DB
// ---------------------------------------------------------------------------

it('returns ApiResponse success envelope with created and skipped lists', function () {
    $campus = app('campus');  // bound in beforeEach
    $semester = Semester::factory()->active()->create();
    $user = nacApiUser();

    $student = makeNacApiStudent($campus, 'SE500001');

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_ENDPOINT_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            [
                'fee_type' => 'bhyt',
                'semester_id' => $semester->id,
                'amount' => 300000,
                'due_date' => now()->addDays(14)->toDateString(),
                'note' => 'API test',
                'csv_file' => csvUpload("student_code\nSE500001\nSE_GHOST\n"),
            ],
        );

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'timestamp',
            'data' => [
                'created' => [['student_code', 'charge_id']],
                'skipped' => [['student_code', 'reason']],
                'summary' => ['total', 'created', 'skipped'],
            ],
        ]);

    $data = $response->json('data');

    expect($data['summary']['created'])->toBe(1)
        ->and($data['summary']['skipped'])->toBe(1)
        ->and($data['created'][0]['student_code'])->toBe('SE500001');

    expect(
        FinanceCharge::where('student_id', $student->id)
            ->where('charge_type', 'bhyt')
            ->where('semester_id', $semester->id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->exists(),
    )->toBeTrue();
});

// ---------------------------------------------------------------------------
// CSV header row skipped; one data row produces exactly one charge
// ---------------------------------------------------------------------------

it('skips the header row and creates one charge for the single data row', function () {
    $campus = app('campus');
    $semester = Semester::factory()->active()->create();
    $user = nacApiUser();

    makeNacApiStudent($campus, 'SE600001');

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_ENDPOINT_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            [
                'fee_type' => 'bhyt',
                'semester_id' => $semester->id,
                'amount' => 200000,
                'due_date' => now()->addDays(7)->toDateString(),
                'csv_file' => csvUpload("student_code\nSE600001\n"),
            ],
        );

    $response->assertStatus(200)->assertJson(['success' => true]);
    expect($response->json('data.summary.created'))->toBe(1);
});
