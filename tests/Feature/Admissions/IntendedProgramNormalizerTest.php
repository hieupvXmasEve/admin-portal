<?php

declare(strict_types=1);

use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Services\Admissions\IntendedProgramNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function normalize(bool $dryRun): array
{
    return app(IntendedProgramNormalizer::class)->run($dryRun);
}

it('normalizes a converted application to the linked student program code', function () {
    $semi = Program::factory()->create(['code' => 'SEMI']);
    $student = Student::factory()->create([
        'program_id' => $semi->id,
        'intake' => 0,
        'intake_semester_id' => Semester::factory()->create()->id,
    ]);

    // Stored label is ambiguous "CS", but the Student really sits in SEMI.
    $application = StudentApplication::factory()->create([
        'intended_program' => 'CS',
        'student_id' => $student->id,
        'status' => 'enrolled',
    ]);

    normalize(dryRun: false);

    expect($application->fresh()->intended_program)->toBe('SEMI');
});

it('maps a pending application label to its canonical code', function () {
    Program::factory()->create(['code' => 'SEMI']);

    $application = StudentApplication::factory()->create([
        'intended_program' => 'Công nghệ bán dẫn',
        'student_id' => null,
        'status' => 'pending',
    ]);

    normalize(dryRun: false);

    expect($application->fresh()->intended_program)->toBe('SEMI');
});

it('leaves an unmappable pending label untouched and reports it', function () {
    $application = StudentApplication::factory()->create([
        'intended_program' => 'Unknown Label',
        'student_id' => null,
        'status' => 'pending',
    ]);

    $report = normalize(dryRun: false);

    expect($application->fresh()->intended_program)->toBe('Unknown Label')
        ->and($report['unmapped'])->toBe(1);
});

it('dry-run reports planned changes without writing', function () {
    Program::factory()->create(['code' => 'BA']);
    $application = StudentApplication::factory()->create([
        'intended_program' => 'Quản trị kinh doanh',
        'student_id' => null,
    ]);

    $report = normalize(dryRun: true);

    expect($report['applied'])->toBeFalse()
        ->and($report['from_label_map'])->toBe(1)
        ->and($application->fresh()->intended_program)->toBe('Quản trị kinh doanh');
});

it('is idempotent — a second apply changes nothing', function () {
    Program::factory()->create(['code' => 'FIN']);
    StudentApplication::factory()->create([
        'intended_program' => 'Tài chính',
        'student_id' => null,
    ]);

    normalize(dryRun: false);
    $second = normalize(dryRun: false);

    expect($second['from_label_map'])->toBe(0)
        ->and($second['from_student'])->toBe(0)
        ->and($second['unchanged'])->toBe(1);
});

it('command runs as a read-only dry-run by default', function () {
    Program::factory()->create(['code' => 'AI']);
    $application = StudentApplication::factory()->create([
        'intended_program' => 'Trí tuệ nhân tạo',
        'student_id' => null,
    ]);

    test()->artisan('applications:normalize-program')->assertSuccessful();

    expect($application->fresh()->intended_program)->toBe('Trí tuệ nhân tạo');
});
