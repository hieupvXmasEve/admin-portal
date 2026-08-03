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
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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
        'student-registered-units-'.now()->format('Y-m-d-His').'.xlsx',
        function (StudentCompletedUnitsExport $export) {
            $cells = collect($export->array())->flatten()->map(fn ($value): string => (string) $value);

            return $cells->contains('SW21999') && $cells->contains($this->campus->name);
        },
    );

    Carbon::setTestNow();
});

it('splits an unfiltered export into one Major column per semester, oldest first', function (): void {
    Excel::fake();

    $user = User::factory()->create();
    actingAs($user);
    grantStudentUnitsExportPermission($user, $this->campus, 'view_academic_report');

    $student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'intake' => 1,
        'intake_semester_id' => Semester::factory(),
    ]);
    $older = Semester::factory()->create(['code' => 'SP25', 'start_date' => '2025-01-01']);
    $newer = Semester::factory()->create(['code' => 'FA25', 'start_date' => '2025-09-01']);

    $olderUnit = Unit::factory()->create(['unit_type' => 'general', 'code' => 'SE001']);
    $newerUnit = Unit::factory()->create(['unit_type' => 'general', 'code' => 'SE002']);
    AcademicRecord::factory()->create(['student_id' => $student->id, 'unit_id' => $olderUnit->id, 'semester_id' => $older->id, 'course_offering_id' => CourseOffering::factory(), 'is_passed' => true]);
    AcademicRecord::factory()->create(['student_id' => $student->id, 'unit_id' => $newerUnit->id, 'semester_id' => $newer->id, 'course_offering_id' => CourseOffering::factory(), 'is_passed' => null]);

    get(route('academic.reports.student-units.export'))->assertOk();

    Excel::assertDownloaded(
        'student-registered-units-'.now()->format('Y-m-d-His').'.xlsx',
        function (StudentCompletedUnitsExport $export): bool {
            $rows = $export->array();
            $header = collect($rows)->first(fn (array $row): bool => in_array('Mã SV', $row, true));
            $dataRow = collect($rows)->first(fn (array $row): bool => ($row[0] ?? null) === 'Mã SV' ? false : in_array('SE001', $row, true));

            $spIndex = array_search('Major SP25', $header, true);
            $faIndex = array_search('Major FA25', $header, true);

            return $spIndex !== false
                && $faIndex !== false
                && $spIndex < $faIndex
                && ! in_array('Môn Major', $header, true)
                && in_array('Trạng thái', $header, true)
                && $dataRow[$spIndex] === 'SE001'
                && $dataRow[$faIndex] === 'SE002';
        },
    );
});

it('keeps a single merged Major column when a semester filter is active', function (): void {
    Excel::fake();

    $user = User::factory()->create();
    actingAs($user);
    grantStudentUnitsExportPermission($user, $this->campus, 'view_academic_report');

    $semester = Semester::factory()->create(['code' => 'SP25', 'start_date' => '2025-01-01']);
    $student = Student::factory()->create(['campus_id' => $this->campus->id, 'intake' => 1, 'intake_semester_id' => Semester::factory()]);
    $unit = Unit::factory()->create(['unit_type' => 'general', 'code' => 'SE001']);
    AcademicRecord::factory()->create(['student_id' => $student->id, 'unit_id' => $unit->id, 'semester_id' => $semester->id, 'course_offering_id' => CourseOffering::factory(), 'is_passed' => true]);

    get(route('academic.reports.student-units.export', ['semester_id' => $semester->id]))->assertOk();

    Excel::assertDownloaded(
        'student-registered-units-'.now()->format('Y-m-d-His').'.xlsx',
        function (StudentCompletedUnitsExport $export): bool {
            $header = collect($export->array())->first(fn (array $row): bool => in_array('Mã SV', $row, true));

            return in_array('Môn Major', $header, true) && ! in_array('Major SP25', $header, true);
        },
    );
});

it('styles a sheet whose semester columns run past column Z', function (): void {
    $rows = collect([[
        'student_id' => 'SW21999',
        'full_name' => 'Wide Sheet',
        'program' => null,
        'status' => 'intake_course',
        'gc' => [],
        'major' => [],
        'units_count' => 30,
        'credits_earned' => '0',
        'credits_required' => '0',
        'units_by_semester' => collect(range(1, 30))
            ->mapWithKeys(fn (int $index): array => [$index => [
                'code' => "S{$index}",
                'sort' => sprintf('2025-01-%02d|%d', min($index, 28), $index),
                'units' => [['code' => "U{$index}", 'name' => "Unit {$index}", 'credits' => '0']],
            ]])
            ->all(),
    ]]);

    $export = new StudentCompletedUnitsExport($rows, [], splitBySemester: true);
    $sheet = (new Spreadsheet)->getActiveSheet();

    $header = collect($export->array())->first(fn (array $row): bool => in_array('Mã SV', $row, true));
    $export->styles($sheet);

    // 5 fixed + 30 semester + 3 trailing = 38 columns, i.e. past 'Z' into 'AL'.
    expect($header)->toHaveCount(38)
        ->and($header[5])->toBe('Major S1')
        ->and($sheet->getColumnDimension('AL')->getAutoSize())->toBeTrue();
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
        'student-registered-units-'.now()->format('Y-m-d-His').'.xlsx',
        fn (StudentCompletedUnitsExport $export): bool => ! collect($export->array())
            ->flatten()
            ->map(fn ($value): string => (string) $value)
            ->contains('OTHER-CAMPUS-EXPORT'),
    );

    Carbon::setTestNow();
});
