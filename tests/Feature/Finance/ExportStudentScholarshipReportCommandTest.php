<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Actions\Reporting\ExportStudentScholarshipApplicationCsvAction;
use App\Modules\Finance\Actions\Reporting\ExportStudentScholarshipRosterCsvAction;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

function createScholarshipExportStudent(string $studentCode = 'SV-SCH-001'): array
{
    $campus = Campus::factory()->create(['name' => 'Hanoi Campus']);
    $program = Program::factory()->create(['name' => 'Computer Science']);
    $semester = Semester::factory()->create([
        'code' => 'SPR2026',
        'name' => 'Spring 2026',
        'start_date' => '2026-01-15 00:00:00',
    ]);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => $studentCode,
            'full_name' => 'Nguyen Van A',
            'intake' => 2026,
            'intake_semester_id' => $semester->id,
        ]);

    return [$student, $semester, $campus, $program];
}

it('exports roster and application csv files via the combined command', function () {
    [$student, $semester] = createScholarshipExportStudent();

    ScholarshipDefinition::query()->create([
        'code' => 'SCH-MERIT',
        'name' => 'Merit Scholarship',
        'type' => 'percentage',
        'amount' => 30,
        'total_amount' => null,
        'total_terms' => null,
        'valid_from' => '2025-01-01',
        'valid_until' => '2027-12-31',
        'is_active' => true,
    ]);

    $award = StudentScholarshipAward::query()->create([
        'student_id' => $student->id,
        'scholarship_code' => 'SCH-MERIT',
        'awarded_at' => '2026-02-01',
        'notes' => 'Top intake',
    ]);

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-SCH-001',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 10000000,
        'discount_total' => 3000000,
        'total_amount' => 7000000,
    ]);

    InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => StudentScholarshipAward::class,
        'description' => 'Scholarship: Merit Scholarship',
        'amount' => 3000000,
        'status' => 'active',
        'reference_id' => $award->id,
    ]);

    $outputDir = storage_path('app/reports/test-scholarship-export-'.uniqid());
    File::ensureDirectoryExists($outputDir);

    $this->artisan('export:student-scholarship-report', ['--output-dir' => $outputDir])
        ->expectsOutputToContain('Export completed.')
        ->assertExitCode(0);

    $rosterFiles = glob($outputDir.'/student-scholarship-roster-*.csv');
    $applicationFiles = glob($outputDir.'/student-scholarship-application-*.csv');

    expect($rosterFiles)->not->toBeEmpty()
        ->and($applicationFiles)->not->toBeEmpty();

    $rosterLines = file($rosterFiles[0], FILE_IGNORE_NEW_LINES);
    $applicationLines = file($applicationFiles[0], FILE_IGNORE_NEW_LINES);

    expect($rosterLines)->not->toBeEmpty()
        ->and(str_getcsv($rosterLines[0]))->toContain('has_scholarship', 'scholarship_code')
        ->and($applicationLines)->toHaveCount(2)
        ->and(str_getcsv($applicationLines[1])[0])->toBe('SV-SCH-001')
        ->and(str_getcsv($applicationLines[1])[4])->toBe('SPR2026')
        ->and(str_getcsv($applicationLines[1])[10])->toBe('3000000.00');

    File::deleteDirectory($outputDir);
});

it('exports only roster when roster-only option is used', function () {
    createScholarshipExportStudent('SV-SCH-002');

    $outputDir = storage_path('app/reports/test-scholarship-roster-only-'.uniqid());
    File::ensureDirectoryExists($outputDir);

    $this->artisan('export:student-scholarship-report', [
        '--output-dir' => $outputDir,
        '--roster-only' => true,
    ])->assertExitCode(0);

    expect(glob($outputDir.'/student-scholarship-roster-*.csv'))->not->toBeEmpty()
        ->and(glob($outputDir.'/student-scholarship-application-*.csv'))->toBeEmpty();

    File::deleteDirectory($outputDir);
});

it('fails when both roster-only and application-only are set', function () {
    $this->artisan('export:student-scholarship-report', [
        '--roster-only' => true,
        '--application-only' => true,
    ])->assertExitCode(1);
});

it('allows ui actions to export roster csv independently', function () {
    [$student] = createScholarshipExportStudent('SV-SCH-003');

    ScholarshipDefinition::query()->create([
        'code' => 'SCH-FIXED',
        'name' => 'Fixed Scholarship',
        'type' => 'fixed_amount',
        'amount' => 5000000,
        'total_amount' => 15000000,
        'total_terms' => 3,
        'valid_from' => '2025-01-01',
        'valid_until' => '2027-12-31',
        'is_active' => true,
    ]);

    StudentScholarshipAward::query()->create([
        'student_id' => $student->id,
        'scholarship_code' => 'SCH-FIXED',
        'awarded_at' => '2026-02-01',
    ]);

    $outputPath = storage_path('app/reports/test-roster-action.csv');
    $result = app(ExportStudentScholarshipRosterCsvAction::class)->handle($outputPath);

    expect($result)->toMatchArray([
        'path' => $outputPath,
        'export_type' => 'roster',
    ])
        ->and($result['row_count'])->toBeGreaterThan(0);

    $lines = file($outputPath, FILE_IGNORE_NEW_LINES);
    $studentRow = collect(array_slice($lines, 1))
        ->map(fn (string $line): array => str_getcsv($line))
        ->firstWhere(fn (array $row): bool => $row[0] === 'SV-SCH-003');

    expect($studentRow[6])->toBe('yes')
        ->and($studentRow[7])->toBe('SCH-FIXED')
        ->and($studentRow[9])->toBe('fixed_amount');

    @unlink($outputPath);
});

it('allows ui actions to export application csv independently', function () {
    [$student, $semester] = createScholarshipExportStudent('SV-SCH-004');

    ScholarshipDefinition::query()->create([
        'code' => 'SCH-APP',
        'name' => 'Application Scholarship',
        'type' => 'percentage',
        'amount' => 10,
        'valid_from' => '2025-01-01',
        'valid_until' => '2027-12-31',
        'is_active' => true,
    ]);

    StudentScholarshipAward::query()->create([
        'student_id' => $student->id,
        'scholarship_code' => 'SCH-APP',
        'awarded_at' => '2026-02-01',
    ]);

    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-SCH-APP',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 20000000,
        'discount_total' => 2000000,
        'total_amount' => 18000000,
    ]);

    InvoiceDiscount::query()->create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => StudentScholarshipAward::class,
        'description' => 'Scholarship: Application Scholarship',
        'amount' => 2000000,
        'status' => 'active',
    ]);

    $outputPath = storage_path('app/reports/test-application-action.csv');
    $result = app(ExportStudentScholarshipApplicationCsvAction::class)->handle($outputPath);

    expect($result['export_type'])->toBe('application')
        ->and($result['row_count'])->toBe(1);

    $row = str_getcsv(file($outputPath, FILE_IGNORE_NEW_LINES)[1]);
    expect($row[0])->toBe('SV-SCH-004')
        ->and($row[4])->toBe('SPR2026')
        ->and($row[7])->toBe('SCH-APP')
        ->and($row[10])->toBe('2000000.00');

    @unlink($outputPath);
});
