<?php

declare(strict_types=1);

use App\Exports\StudentCompletedUnitsExport;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\CourseOffering;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function grantStudentUnitsExportPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'student-units-export_'.Str::lower(Str::random(10))]);

    RolePermission::create([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);
    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
    ]);
}

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
});

it('downloads the filtered result as xlsx with the campus name in the header', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00'));
    Excel::fake();

    $user = User::factory()->create();
    actingAs($user);
    grantStudentUnitsExportPermission($user, $this->campus, 'view_academic_report');

    $student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'student_id' => 'SW21999',
        'intake' => 1,
        'intake_semester_id' => Semester::factory(),
    ]);
    $unit = Unit::factory()->create(['unit_type' => 'egc', 'code' => 'EGCF']);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'unit_id' => $unit->id,
        'course_offering_id' => CourseOffering::factory(),
        'is_passed' => true,
    ]);

    get(route('academic.reports.student-units.export'))->assertOk();

    Excel::assertDownloaded(
        'student-completed-units-'.now()->format('Y-m-d-His').'.xlsx',
        function (StudentCompletedUnitsExport $export) {
            $cells = collect($export->array())->flatten()->map(fn ($value): string => (string) $value);

            return $cells->contains('SW21999') && $cells->contains($this->campus->name);
        },
    );

    Carbon::setTestNow();
});

it('forbids export without view_academic_report', function (): void {
    actingAs(User::factory()->create());

    get(route('academic.reports.student-units.export'))->assertForbidden();
});

it('ignores a client-supplied campus_id on export and stays scoped to the session campus', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-29 11:00:00'));
    Excel::fake();

    $user = User::factory()->create();
    actingAs($user);
    grantStudentUnitsExportPermission($user, $this->campus, 'view_academic_report');

    $otherCampus = Campus::factory()->create();
    Student::factory()->create([
        'campus_id' => $otherCampus->id,
        'student_id' => 'OTHER-CAMPUS-EXPORT',
        'intake' => 1,
        'intake_semester_id' => Semester::factory(),
    ]);

    get(route('academic.reports.student-units.export', ['campus_id' => $otherCampus->id]))->assertOk();

    Excel::assertDownloaded(
        'student-completed-units-'.now()->format('Y-m-d-His').'.xlsx',
        fn (StudentCompletedUnitsExport $export): bool => ! collect($export->array())
            ->flatten()
            ->map(fn ($value): string => (string) $value)
            ->contains('OTHER-CAMPUS-EXPORT'),
    );

    Carbon::setTestNow();
});
