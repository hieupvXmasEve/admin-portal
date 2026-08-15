<?php

declare(strict_types=1);

use App\Exports\StudentApplicationExport;
use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Upload\Models\ApplicationDocument;
use App\Modules\Upload\Models\ApplicationDocumentType;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn(['view_student_application']);
    $this->app->singleton(CampusPermissionReader::class, fn () => $permissionService);

    $this->app->singleton('campus', fn () => $this->campus);
    session(['current_campus_id' => $this->campus->id]);

    $this->staff = User::factory()->create(['type' => UserType::STAFF]);
});

/**
 * Combine the export's column headings with a single application's mapped row,
 * so assertions read by column name and survive column reordering.
 *
 * @return array<string, mixed>
 */
function exportRow(StudentApplication $application): array
{
    $export = new StudentApplicationExport(StudentApplication::query());
    $headings = $export->headings();
    $values = $export->map($application);

    expect($values)->toHaveCount(count($headings));

    return array_combine($headings, $values);
}

it('leaves missing fields blank instead of writing N/A', function () {
    $application = StudentApplication::factory()->pending()->create([
        'campus_code' => $this->campus->code,
        'student_code' => 'S1234567',
        'crm_admission_id' => 'CRM-9001',
        'ethnicity' => null,
        'address' => null,
        'sut_id' => null,
    ]);

    $row = exportRow($application);

    // The whole point of the export: blanks the CRM can fill, not "N/A".
    expect($row['Ethnicity'])->toBe('');
    expect($row['Address'])->toBe('');
    expect($row['SUT ID'])->toBe('');
    expect($row)->not->toContain('N/A');
});

it('includes the CRM keys the CRM uses to match a row back', function () {
    $application = StudentApplication::factory()->pending()->create([
        'campus_code' => $this->campus->code,
        'student_code' => 'S7654321',
        'crm_admission_id' => 'CRM-4242',
    ]);

    $row = exportRow($application);

    expect($row['Student Code'])->toBe('S7654321');
    expect($row['CRM Admission ID'])->toBe('CRM-4242');
});

it('includes the primary guardian columns', function () {
    $application = StudentApplication::factory()->pending()->create([
        'campus_code' => $this->campus->code,
        'student_code' => 'S1112223',
    ]);

    ApplicationGuardian::factory()->primary()->forApplication($application)->create([
        'full_name' => 'Nguyen Van A',
        'phone' => '0900000001',
        'email' => 'guardian@example.com',
    ]);

    $row = exportRow($application);

    expect($row['Primary Guardian Name'])->toBe('Nguyen Van A');
    expect($row['Primary Guardian Phone'])->toBe('0900000001');
    expect($row['Primary Guardian Email'])->toBe('guardian@example.com');
});

it('emits one column per active document type, blank when missing', function () {
    ApplicationDocumentType::factory()->create(['code' => 'cccd', 'name' => 'CCCD', 'order' => 1]);
    ApplicationDocumentType::factory()->create(['code' => 'transcript', 'name' => 'Transcript', 'order' => 2]);

    $application = StudentApplication::factory()->pending()->create([
        'campus_code' => $this->campus->code,
        'student_code' => 'S3334445',
    ]);

    ApplicationDocument::factory()->forApplication($application)->create([
        'file_type_code' => 'cccd',
        'link' => 'https://drive.example.com/file/cccd-1',
    ]);

    $row = exportRow($application);

    // Present → the file link; missing → blank (so the gap is visible).
    expect($row['CCCD'])->toContain('https://drive.example.com/file/cccd-1');
    expect($row['Transcript'])->toBe('');
});

it('downloads an xlsx scoped to the current campus', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-28 10:00:00'));
    Excel::fake();

    StudentApplication::factory()->pending()->create([
        'campus_code' => $this->campus->code,
        'student_code' => 'S0001111',
    ]);
    StudentApplication::factory()->pending()->create([
        'campus_code' => $this->otherCampus->code, // must not be exported
        'student_code' => 'S0002222',
    ]);

    $campusCode = $this->campus->code;

    $this->actingAs($this->staff)
        ->get(route('student-applications.export', ['format' => 'xlsx', 'scope' => 'all']))
        ->assertOk();

    Excel::assertDownloaded(
        'student_applications_2026-06-28_10-00-00.xlsx',
        function (StudentApplicationExport $export) use ($campusCode) {
            $campuses = $export->query()->get()->pluck('campus_code')->unique()->values()->all();

            return $campuses === [$campusCode];
        }
    );

    Carbon::setTestNow();
});
