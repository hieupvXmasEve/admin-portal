<?php

declare(strict_types=1);

use App\Models\AcademicProgressionEvent;
use App\Models\Campus;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
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
// alongside PlacementWorklistTest.php in the same Pest process — Pest test
// files share one PHP global namespace, unlike PHPUnit test classes.
function grantChangeStudentStatusForBulkEgcImport(User $user, Campus $campus): void
{
    $role = Role::firstOrCreate(['code' => 'bulk_egc_import_test'], ['name' => 'Bulk EGC Import Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'change_student_status'],
        ['name' => 'change_student_status', 'display_name' => 'change_student_status', 'module' => 'students', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);
}

function makeBulkEgcWorklistStudent(Campus $campus, array $studentAttributes = [], ?string $studyStage = null, string $enrollmentStatus = 'pending'): Student
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
    grantChangeStudentStatusForBulkEgcImport($this->user, $this->campus);
    session(['current_campus_id' => $this->campus->id]);
    actingAs($this->user);

    $this->intakeSemester = Semester::factory()->create();
    app(CrmMappingSettings::class)->setIntakeCode($this->intakeSemester->code);

    $this->foundationStudent = makeBulkEgcWorklistStudent($this->campus, ['student_id' => 'STU1001']);
    $this->egc3Student = makeBulkEgcWorklistStudent($this->campus, ['student_id' => 'STU1002']);
    $this->gcsStudent = makeBulkEgcWorklistStudent($this->campus, ['student_id' => 'STU1003']);
    $this->egc6Student = makeBulkEgcWorklistStudent($this->campus, ['student_id' => 'STU1004']);
    $this->blankLevelStudent = makeBulkEgcWorklistStudent($this->campus, ['student_id' => 'STU1005']);
    $this->alreadyPlacedStudent = makeBulkEgcWorklistStudent($this->campus, ['student_id' => 'STU1006'], 'intake_pre_uni_gc', 'active');
});

function bulkEgcCsvFile(): UploadedFile
{
    $rows = implode("\n", [
        'Full name,Student ID,Email,University,Type,Level,Class,CP,Note',
        'A,STU1001,,,,Foundation,,,',
        'B,STU1002,,,,EGC3,,,',
        'C,STU1003,,,,GCS5,,,',
        'D,STU1004,,,,EGC6,,,',
        'E,STU1005,,,,,,,',
        'F,UNKNOWN001,,,,EGC2,,,',
        'G,STU1006,,,,EGC2,,,',
    ]);

    return UploadedFile::fake()->createWithContent('student_egc.csv', $rows);
}

it('previews without writing any placement data', function (): void {
    $response = post(route('students.placement-worklist.bulk-import.preview'), ['file' => bulkEgcCsvFile()]);

    $response->assertOk();
    $data = $response->json('data');

    expect($data['summary']['valid'])->toBe(3)
        ->and($data['summary']['skipped'])->toBe(4)
        ->and($data['summary']['skip_counts']['level_excluded_gcs'])->toBe(1)
        ->and($data['summary']['skip_counts']['level_unmapped'])->toBe(1)
        ->and($data['summary']['skip_counts']['level_missing'])->toBe(1)
        ->and($data['summary']['skip_counts']['student_not_found'])->toBe(1);

    expect(StudentActionLog::query()->count())->toBe(0)
        ->and(AcademicProgressionEvent::query()->count())->toBe(0);
});

it('executes only the valid, not-yet-placed rows', function (): void {
    $preview = post(route('students.placement-worklist.bulk-import.preview'), ['file' => bulkEgcCsvFile()]);
    $previewToken = $preview->json('data.preview_token');

    $response = post(route('students.placement-worklist.bulk-import.execute'), [
        'file' => bulkEgcCsvFile(),
        'preview_token' => $previewToken,
    ]);

    $response->assertOk();
    $data = $response->json('data');

    expect($data['summary']['success'])->toBe(2)
        ->and($data['summary']['failed'])->toBe(1);

    expect(StudentActionLog::query()->count())->toBe(2)
        ->and(AcademicProgressionEvent::query()->where('event_type', 'PLACEMENT_INITIALIZED')->count())->toBe(2);

    expect(ProgramEnrollment::query()->where('student_id', $this->foundationStudent->id)->sole()->egc_current_level)->toBe(0);
    expect(ProgramEnrollment::query()->where('student_id', $this->egc3Student->id)->sole()->egc_current_level)->toBe(3);

    $alreadyPlacedRow = collect($data['rows'])->firstWhere('student.student_id', 'STU1006');
    expect($alreadyPlacedRow['status'])->toBe('error');
});

it('rejects both routes without change_student_status', function (): void {
    actingAs(User::factory()->create());

    post(route('students.placement-worklist.bulk-import.preview'), ['file' => bulkEgcCsvFile()])->assertForbidden();
    post(route('students.placement-worklist.bulk-import.execute'), ['file' => bulkEgcCsvFile(), 'preview_token' => 'x'])->assertForbidden();
});

it('fails fast when the intake semester is not configured', function (): void {
    DB::table('crm_value_mappings')->where('kind', 'intake')->delete();

    $preview = post(route('students.placement-worklist.bulk-import.preview'), ['file' => bulkEgcCsvFile()]);
    $preview->assertOk();
    expect($preview->json('data.global_errors.0'))->toContain('Intake semester is not configured');
    expect($preview->json('data.summary.valid'))->toBe(0);

    // A valid preview_token still short-circuits at the semester guard inside
    // the action (not the controller's token check), so execute here must
    // also return 200 with global_errors, not a 422 from a bad-token path.
    $previewToken = $preview->json('data.preview_token');
    $execute = post(route('students.placement-worklist.bulk-import.execute'), [
        'file' => bulkEgcCsvFile(),
        'preview_token' => $previewToken,
    ]);
    $execute->assertOk();
    expect($execute->json('data.global_errors.0'))->toContain('Intake semester is not configured');
    expect($execute->json('data.summary.success'))->toBe(0);

    expect(StudentActionLog::query()->count())->toBe(0)
        ->and(AcademicProgressionEvent::query()->count())->toBe(0);
});

it('rejects execute with an invalid or stale preview token', function (): void {
    post(route('students.placement-worklist.bulk-import.execute'), [
        'file' => bulkEgcCsvFile(),
        'preview_token' => 'not-a-real-token',
    ])->assertStatus(422);

    expect(StudentActionLog::query()->count())->toBe(0);
});
