<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Support\BulkEgcPlacementRowMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeStudentWithId(string $studentId, ?Campus $campus = null): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->create([
        'student_id' => $studentId,
        'intake' => 1,
        'intake_semester_id' => $semester->id,
        'campus_id' => ($campus ?? Campus::factory()->create())->id,
    ]);
}

it('finds Student ID and Level columns regardless of surrounding columns', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;

    $result = $mapper->validateHeaders(['Full name', 'Student ID', 'Email', 'University', 'Type', 'Level', 'Class', 'CP', 'Note']);

    expect($result['ok'])->toBeTrue()
        ->and($result['student_id_col'])->toBe(1)
        ->and($result['level_col'])->toBe(5);
});

it('reports missing headers when Student ID or Level is absent', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;

    $result = $mapper->validateHeaders(['Full name', 'Email']);

    expect($result['ok'])->toBeFalse()
        ->and($result['missing'])->toBe(['Student ID', 'Level']);
});

it('excludes GCS levels', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;
    $student = makeStudentWithId('STU0001');

    foreach (['GCS5', 'GCS', 'gcs1'] as $level) {
        $row = $mapper->mapRow(['STU0001', $level], 2, 0, 1, $student->campus_id);
        expect($row['status'])->toBe('skip')
            ->and($row['skip_reason'])->toBe('level_excluded_gcs');
    }
});

it('treats EGC6 and unrecognized values as unmapped', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;
    $student = makeStudentWithId('STU0002');

    foreach (['EGC6', 'x'] as $level) {
        $row = $mapper->mapRow(['STU0002', $level], 2, 0, 1, $student->campus_id);
        expect($row['status'])->toBe('skip')
            ->and($row['skip_reason'])->toBe('level_unmapped');
    }
});

it('maps Foundation and EGC1-5 case-insensitively', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;
    $student = makeStudentWithId('STU0003');

    $expectations = [
        'Foundation' => 0, 'foundation' => 0, 'FOUNDATION' => 0,
        'EGC1' => 1, 'EGC2' => 2, 'EGC3' => 3, 'EGC4' => 4, 'EGC5' => 5,
    ];

    foreach ($expectations as $raw => $expectedLevel) {
        $row = $mapper->mapRow(['STU0003', $raw], 2, 0, 1, $student->campus_id);
        expect($row['status'])->toBe('valid')
            ->and($row['level'])->toBe($expectedLevel)
            ->and($row['normalized_payload'])->toBe(['student_id' => $row['student']['id'], 'english_level' => $expectedLevel]);
    }
});

it('reports missing student id and unknown student id separately', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;
    $campus = Campus::factory()->create();

    $missing = $mapper->mapRow(['', 'EGC1'], 2, 0, 1, $campus->id);
    expect($missing['status'])->toBe('skip')
        ->and($missing['skip_reason'])->toBe('student_id_missing');

    $notFound = $mapper->mapRow(['NOPE0001', 'EGC1'], 2, 0, 1, $campus->id);
    expect($notFound['status'])->toBe('skip')
        ->and($notFound['skip_reason'])->toBe('student_not_found');
});

it('reports a missing level for an existing student', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;
    $student = makeStudentWithId('STU0004');

    $row = $mapper->mapRow(['STU0004', ''], 2, 0, 1, $student->campus_id);

    expect($row['status'])->toBe('skip')
        ->and($row['skip_reason'])->toBe('level_missing');
});

it('treats a student that exists only in another campus as not found', function (): void {
    $mapper = new BulkEgcPlacementRowMapper;
    $otherCampus = Campus::factory()->create();
    $currentCampus = Campus::factory()->create();
    makeStudentWithId('STU0005', $otherCampus);

    $row = $mapper->mapRow(['STU0005', 'EGC1'], 2, 0, 1, $currentCampus->id);

    expect($row['status'])->toBe('skip')
        ->and($row['skip_reason'])->toBe('student_not_found');
});
