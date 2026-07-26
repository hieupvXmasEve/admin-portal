<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

// Route uses ['web','auth'] middleware which enforces CSRF verification.
// Seed a known token into the session and attach it as X-CSRF-TOKEN header.
const NAC_REQUEST_TEST_CSRF = 'nac-request-test-csrf';

beforeEach(function () {
    $campus = Campus::factory()->create();
    app()->singleton('campus', fn () => $campus);

    session([
        '_token' => NAC_REQUEST_TEST_CSRF,
        'current_campus_id' => $campus->id,
    ]);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeAuthorizedNacUser(): User
{
    $user = User::factory()->create();

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_finance_operations_generate_charges']);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    return $user;
}

function validNacPayload(int $semesterId): array
{
    return [
        'fee_type' => 'bhyt',
        'semester_id' => $semesterId,
        'amount' => 500000,
        'due_date' => now()->addDays(30)->toDateString(),
        'note' => 'Optional note',
    ];
}

function fakeCsv(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('students.csv', "student_code\nSE100001\n");
}

// ---------------------------------------------------------------------------
// Authorization gate (F2)
// ---------------------------------------------------------------------------

it('returns 403 when user lacks view_finance_operations_generate_charges permission', function () {
    $semester = Semester::factory()->active()->create();
    $user = User::factory()->create();

    $mock = Mockery::mock(CampusPermissionReader::class);
    $mock->shouldReceive('permissionCodesForUserId')->andReturn([]);
    app()->singleton(CampusPermissionReader::class, fn () => $mock);

    $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            array_merge(validNacPayload($semester->id), ['csv_file' => fakeCsv()]),
        )
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Helpers — app uses custom error envelope {errors: [{code, field, detail}]}
// so we check for the field name in the errors array instead of Laravel's default format
// ---------------------------------------------------------------------------

/** Assert the response has a validation error for the given field (custom ApiResponse format). */
function assertNacValidationError(TestResponse $response, string $field): void
{
    $response->assertStatus(422)
        ->assertJson(['success' => false]);

    $fields = collect($response->json('errors'))->pluck('field')->all();
    expect(in_array($field, $fields, true))->toBeTrue(
        "Expected validation error for field '{$field}' but got: ".implode(', ', $fields),
    );
}

// ---------------------------------------------------------------------------
// Validation: invalid fee_type (not in NonAcademicChargeTypeEnum)
// ---------------------------------------------------------------------------

it('rejects fee_type values not in the non-academic enum', function () {
    $semester = Semester::factory()->active()->create();
    $user = makeAuthorizedNacUser();

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            array_merge(validNacPayload($semester->id), [
                'fee_type' => 'tuition_term',  // valid DB value, not a non-academic type
                'csv_file' => fakeCsv(),
            ]),
        );

    assertNacValidationError($response, 'fee_type');
});

// ---------------------------------------------------------------------------
// Validation: semester_id required
// ---------------------------------------------------------------------------

it('rejects a request missing semester_id', function () {
    $semester = Semester::factory()->active()->create();
    $user = makeAuthorizedNacUser();

    $payload = validNacPayload($semester->id);
    unset($payload['semester_id']);
    $payload['csv_file'] = fakeCsv();

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(route('api.finance.operations.generate-non-academic-charges'), $payload);

    assertNacValidationError($response, 'semester_id');
});

// ---------------------------------------------------------------------------
// Validation: due_date must not be in the past
// ---------------------------------------------------------------------------

it('rejects a due_date in the past', function () {
    $semester = Semester::factory()->active()->create();
    $user = makeAuthorizedNacUser();

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            array_merge(validNacPayload($semester->id), [
                'due_date' => now()->subDay()->toDateString(),
                'csv_file' => fakeCsv(),
            ]),
        );

    assertNacValidationError($response, 'due_date');
});

// ---------------------------------------------------------------------------
// Validation: amount must be ≥ 1
// ---------------------------------------------------------------------------

it('rejects amount of zero', function () {
    $semester = Semester::factory()->active()->create();
    $user = makeAuthorizedNacUser();

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            array_merge(validNacPayload($semester->id), [
                'amount' => 0,
                'csv_file' => fakeCsv(),
            ]),
        );

    assertNacValidationError($response, 'amount');
});

// ---------------------------------------------------------------------------
// Validation: csv_file is required
// ---------------------------------------------------------------------------

it('rejects a request with no csv_file', function () {
    $semester = Semester::factory()->active()->create();
    $user = makeAuthorizedNacUser();

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            validNacPayload($semester->id),
        );

    assertNacValidationError($response, 'csv_file');
});

// ---------------------------------------------------------------------------
// P2-F Test 1: CSV header column named 'code' instead of 'student_code' → 422
// ---------------------------------------------------------------------------

it('rejects csv_file when the header row uses "code" instead of "student_code"', function () {
    $semester = Semester::factory()->active()->create();
    $user = makeAuthorizedNacUser();

    // CSV with wrong header 'code' — the controller expects 'student_code' as the only valid header.
    // A file containing only a 'code' header means no valid data rows are parsed → 422.
    $wrongHeaderCsv = UploadedFile::fake()->createWithContent(
        'students.csv',
        "code\nSE100001\nSE100002\n",
    );

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            array_merge(validNacPayload($semester->id), ['csv_file' => $wrongHeaderCsv]),
        );

    // The controller treats an unrecognised header as a data row (not skipped), which means
    // 'code' gets passed as a student code — it is not a valid student code format but the
    // behaviour is: the request is accepted and that row skipped. The story-pack mandates a
    // 422 response for wrong headers. We implement this by checking for the 'student_code'
    // header at parse time and rejecting if the header is absent.
    // Re: the spec (P2-F Test 1): "CSV header is `code` instead of `student_code` → 422".
    $response->assertStatus(422);
});

// ---------------------------------------------------------------------------
// P2-F Test 2: CSV with 1001 data rows → 422 row-limit error
// ---------------------------------------------------------------------------

it('rejects csv_file with more than 1000 data rows', function () {
    $semester = Semester::factory()->active()->create();
    $user = makeAuthorizedNacUser();

    // Build a CSV with 1001 data rows (student_code header + 1001 lines)
    $lines = ['student_code'];
    for ($i = 1; $i <= 1001; $i++) {
        $lines[] = sprintf('SE%07d', $i);
    }
    $csvContent = implode("\n", $lines)."\n";

    $bigCsv = UploadedFile::fake()->createWithContent('big.csv', $csvContent);

    $response = $this->actingAs($user)
        ->withHeaders(['X-CSRF-TOKEN' => NAC_REQUEST_TEST_CSRF])
        ->post(
            route('api.finance.operations.generate-non-academic-charges'),
            array_merge(validNacPayload($semester->id), ['csv_file' => $bigCsv]),
        );

    $response->assertStatus(422);

    // Ensure the error message mentions the row limit
    $body = $response->json();
    $errorTexts = collect($body['errors'] ?? [])->pluck('detail')->implode(' ');
    expect($errorTexts)->toContain('1000');
});
