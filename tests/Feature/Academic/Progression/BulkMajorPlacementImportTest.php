<?php

declare(strict_types=1);

use App\Models\AcademicProgressionEvent;
use App\Models\Campus;
use App\Models\IeltsCertificate;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

// Suffixed to avoid a global-function redeclare fatal when this file runs
// alongside PlacementWorklistTest.php / BulkEgcPlacementImportTest.php in the
// same Pest process — Pest test files share one PHP global namespace.
function grantChangeStudentStatusForBulkMajorImport(User $user, Campus $campus): void
{
    $role = Role::firstOrCreate(['code' => 'bulk_major_import_test'], ['name' => 'Bulk Major Import Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'change_student_status'],
        ['name' => 'change_student_status', 'display_name' => 'change_student_status', 'module' => 'students', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);
}

function makeBulkMajorWorklistStudent(Campus $campus, array $studentAttributes = [], ?string $studyStage = null, string $enrollmentStatus = 'pending'): Student
{
    $semester = Semester::factory()->create();
    $student = Student::factory()
        ->for($campus)
        ->for(Program::factory())
        ->create([
            'intake' => $semester->id,
            'intake_semester_id' => $semester->id,
            'intake_mode' => 'sequential',
            ...$studentAttributes,
        ]);

    ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => $enrollmentStatus,
        'study_stage' => $studyStage,
        'source_type' => ProgramEnrollment::LEGACY_STUDENT_SOURCE,
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);

    return $student;
}

beforeEach(function (): void {
    Cache::flush();
    $this->withoutMiddleware([PreventRequestForgery::class, VerifyCsrfToken::class]);

    $this->campus = Campus::factory()->create();
    $this->user = User::factory()->create();
    grantChangeStudentStatusForBulkMajorImport($this->user, $this->campus);
    session(['current_campus_id' => $this->campus->id]);
    actingAs($this->user);

    $this->intakeSemester = Semester::factory()->create();
    app(CrmMappingSettings::class)->setIntakeCode($this->intakeSemester->code);

    $this->qualifiedStudent = makeBulkMajorWorklistStudent($this->campus, ['student_id' => 'STU3001']);
    StudentApplication::factory()->create([
        'student_id' => $this->qualifiedStudent->id,
        'english_test_type' => 'IELTS',
        'overall' => 6.5,
        'exam_date' => '2026-01-15',
    ]);

    $this->belowThresholdStudent = makeBulkMajorWorklistStudent($this->campus, ['student_id' => 'STU3002']);
    StudentApplication::factory()->create([
        'student_id' => $this->belowThresholdStudent->id,
        'english_test_type' => 'IELTS',
        'overall' => 5.0,
    ]);

    $this->otherTestStudent = makeBulkMajorWorklistStudent($this->campus, ['student_id' => 'STU3003']);
    StudentApplication::factory()->create([
        'student_id' => $this->otherTestStudent->id,
        'english_test_type' => 'Other',
        'overall' => 8.0,
    ]);

    $this->noApplicationStudent = makeBulkMajorWorklistStudent($this->campus, ['student_id' => 'STU3004']);

    $this->alreadyPlacedStudent = makeBulkMajorWorklistStudent($this->campus, ['student_id' => 'STU3005'], 'intake_course', 'active');
    StudentApplication::factory()->create([
        'student_id' => $this->alreadyPlacedStudent->id,
        'english_test_type' => 'IELTS',
        'overall' => 7.0,
    ]);
});

function bulkMajorCsvFile(): UploadedFile
{
    $rows = implode("\n", [
        'Student ID,Note',
        'STU3001,',
        'STU3002,',
        'STU3003,',
        'STU3004,',
        'UNKNOWN001,',
        'STU3005,',
    ]);

    return UploadedFile::fake()->createWithContent('student_major.csv', $rows);
}

it('previews without writing any placement data', function (): void {
    $response = post(route('students.placement-worklist.bulk-import-major.preview'), ['file' => bulkMajorCsvFile()]);

    $response->assertOk();
    $data = $response->json('data');

    expect($data['summary']['valid'])->toBe(2)
        ->and($data['summary']['skipped'])->toBe(4)
        ->and($data['summary']['skip_counts']['below_threshold_excluded'])->toBe(1)
        ->and($data['summary']['skip_counts']['application_score_missing'])->toBe(2)
        ->and($data['summary']['skip_counts']['student_not_found'])->toBe(1);

    expect(IeltsCertificate::query()->count())->toBe(0)
        ->and(StudentActionLog::query()->count())->toBe(0)
        ->and(AcademicProgressionEvent::query()->count())->toBe(0);
});

it('executes only the at-or-above-threshold, not-yet-placed rows, always into Major', function (): void {
    $preview = post(route('students.placement-worklist.bulk-import-major.preview'), ['file' => bulkMajorCsvFile()]);
    $previewToken = $preview->json('data.preview_token');

    $response = post(route('students.placement-worklist.bulk-import-major.execute'), [
        'file' => bulkMajorCsvFile(),
        'preview_token' => $previewToken,
    ]);

    $response->assertOk();
    $data = $response->json('data');

    expect($data['summary']['success'])->toBe(1)
        ->and($data['summary']['failed'])->toBe(1);

    expect(IeltsCertificate::query()->count())->toBe(1)
        ->and(StudentActionLog::query()->count())->toBe(1)
        ->and(AcademicProgressionEvent::query()->where('event_type', 'PLACEMENT_INITIALIZED')->count())->toBe(1);

    $enrollment = ProgramEnrollment::query()->where('student_id', $this->qualifiedStudent->id)->sole();
    expect($enrollment->study_stage)->toBe('intake_course');

    expect(IeltsCertificate::query()->where('student_id', $this->belowThresholdStudent->id)->count())->toBe(0);

    $alreadyPlacedRow = collect($data['rows'])->firstWhere('student.student_id', 'STU3005');
    expect($alreadyPlacedRow['status'])->toBe('error');
});

it('rejects both routes without change_student_status', function (): void {
    actingAs(User::factory()->create());

    post(route('students.placement-worklist.bulk-import-major.preview'), ['file' => bulkMajorCsvFile()])->assertForbidden();
    post(route('students.placement-worklist.bulk-import-major.execute'), ['file' => bulkMajorCsvFile(), 'preview_token' => 'x'])->assertForbidden();
});

it('fails fast when the intake semester is not configured', function (): void {
    DB::table('crm_value_mappings')->where('kind', 'intake')->delete();

    $preview = post(route('students.placement-worklist.bulk-import-major.preview'), ['file' => bulkMajorCsvFile()]);
    $preview->assertOk();
    expect($preview->json('data.global_errors.0'))->toContain('Intake semester is not configured');
    expect($preview->json('data.summary.valid'))->toBe(0);

    $previewToken = $preview->json('data.preview_token');
    $execute = post(route('students.placement-worklist.bulk-import-major.execute'), [
        'file' => bulkMajorCsvFile(),
        'preview_token' => $previewToken,
    ]);
    $execute->assertOk();
    expect($execute->json('data.global_errors.0'))->toContain('Intake semester is not configured');
    expect($execute->json('data.summary.success'))->toBe(0);

    expect(StudentActionLog::query()->count())->toBe(0)
        ->and(AcademicProgressionEvent::query()->count())->toBe(0);
});

it('rejects execute with an invalid or stale preview token', function (): void {
    post(route('students.placement-worklist.bulk-import-major.execute'), [
        'file' => bulkMajorCsvFile(),
        'preview_token' => 'not-a-real-token',
    ])->assertStatus(422);

    expect(StudentActionLog::query()->count())->toBe(0);
});

it('rejects a row that only became valid after the preview was taken, without a global 422', function (): void {
    // STU3004 has no application at preview time (skip: application_score_missing).
    $preview = post(route('students.placement-worklist.bulk-import-major.preview'), ['file' => bulkMajorCsvFile()]);
    $previewToken = $preview->json('data.preview_token');
    $previewRow = collect($preview->json('data.rows'))->firstWhere('student.student_id', 'STU3004');
    expect($previewRow['status'])->toBe('skip');

    // A qualifying score arrives after preview, before execute.
    StudentApplication::factory()->create([
        'student_id' => $this->noApplicationStudent->id,
        'english_test_type' => 'IELTS',
        'overall' => 7.0,
    ]);

    // Same file (same hash) so the token/hash guard passes; only the DB changed.
    $response = post(route('students.placement-worklist.bulk-import-major.execute'), [
        'file' => bulkMajorCsvFile(),
        'preview_token' => $previewToken,
    ]);

    $response->assertOk();
    $row = collect($response->json('data.rows'))->firstWhere('student.student_id', 'STU3004');
    expect($row['status'])->toBe('error');
    expect(IeltsCertificate::query()->where('student_id', $this->noApplicationStudent->id)->count())->toBe(0);
});

it('numbers rows by their real 1-based spreadsheet offset', function (): void {
    $response = post(route('students.placement-worklist.bulk-import-major.preview'), ['file' => bulkMajorCsvFile()]);

    $rows = collect($response->json('data.rows'));
    expect($rows->firstWhere('student.student_id', 'STU3001')['row_number'])->toBe(2);
});
