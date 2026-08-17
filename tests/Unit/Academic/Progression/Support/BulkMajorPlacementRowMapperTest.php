<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentApplication;
use App\Modules\Academic\Progression\Support\BulkMajorPlacementRowMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMajorImportStudentWithId(string $studentId, ?Campus $campus = null): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->create([
        'student_id' => $studentId,
        'intake' => 1,
        'intake_semester_id' => $semester->id,
        'campus_id' => ($campus ?? Campus::factory()->create())->id,
    ]);
}

it('finds the Student ID column regardless of other columns', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;

    $result = $mapper->validateHeaders(['Full name', 'Student ID', 'Email']);

    expect($result['ok'])->toBeTrue()
        ->and($result['student_id_col'])->toBe(1);
});

it('reports missing headers when Student ID is absent', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;

    $result = $mapper->validateHeaders(['Full name', 'Email']);

    expect($result['ok'])->toBeFalse()
        ->and($result['missing'])->toBe(['Student ID']);
});

it('excludes a student found only in another campus', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $otherCampus = Campus::factory()->create();
    $currentCampus = Campus::factory()->create();
    makeMajorImportStudentWithId('STU2001', $otherCampus);

    $row = $mapper->mapRow(['STU2001'], 2, 0, $currentCampus->id);

    expect($row['status'])->toBe('skip')
        ->and($row['skip_reason'])->toBe('student_not_found');
});

it('reports missing student id and unknown student id separately', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $campus = Campus::factory()->create();

    $missing = $mapper->mapRow([''], 2, 0, $campus->id);
    expect($missing['status'])->toBe('skip')
        ->and($missing['skip_reason'])->toBe('student_id_missing');

    $notFound = $mapper->mapRow(['NOPE0001'], 2, 0, $campus->id);
    expect($notFound['status'])->toBe('skip')
        ->and($notFound['skip_reason'])->toBe('student_not_found');
});

it('skips a student whose only application is not IELTS', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $student = makeMajorImportStudentWithId('STU2002');
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'Other',
        'overall' => 7.0,
    ]);

    $row = $mapper->mapRow(['STU2002'], 2, 0, $student->campus_id);

    expect($row['status'])->toBe('skip')
        ->and($row['skip_reason'])->toBe('application_score_missing');
});

it('skips a student whose IELTS application has a null overall score', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $student = makeMajorImportStudentWithId('STU2003');
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'IELTS',
        'overall' => null,
    ]);

    $row = $mapper->mapRow(['STU2003'], 2, 0, $student->campus_id);

    expect($row['status'])->toBe('skip')
        ->and($row['skip_reason'])->toBe('application_score_missing');
});

it('picks the most recent qualifying IELTS application when multiple exist', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $student = makeMajorImportStudentWithId('STU2004');
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'IELTS',
        'overall' => 5.0,
        'created_at' => now()->subDays(10),
    ]);
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'IELTS',
        'overall' => 7.5,
        'created_at' => now(),
    ]);

    $row = $mapper->mapRow(['STU2004'], 2, 0, $student->campus_id);

    expect($row['overall_score'])->toBe(7.5);
});

it('marks the row valid at or above the 5.5 threshold and excludes it below', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;

    $validStudent = makeMajorImportStudentWithId('STU2005');
    StudentApplication::factory()->create([
        'student_id' => $validStudent->id,
        'english_test_type' => 'IELTS',
        'overall' => 5.5,
    ]);
    $validRow = $mapper->mapRow(['STU2005'], 2, 0, $validStudent->campus_id);
    expect($validRow['status'])->toBe('valid')
        ->and($validRow['normalized_payload']['ielts_score'])->toBe(5.5);

    $belowStudent = makeMajorImportStudentWithId('STU2006');
    StudentApplication::factory()->create([
        'student_id' => $belowStudent->id,
        'english_test_type' => 'IELTS',
        'overall' => 5.4,
    ]);
    $belowRow = $mapper->mapRow(['STU2006'], 2, 0, $belowStudent->campus_id);
    expect($belowRow['status'])->toBe('skip')
        ->and($belowRow['skip_reason'])->toBe('below_threshold_excluded');
});

it('excludes a wrong-scale score above the plausible IELTS band', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $student = makeMajorImportStudentWithId('STU2008');
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'IELTS',
        'overall' => 65.0,
    ]);

    $row = $mapper->mapRow(['STU2008'], 2, 0, $student->campus_id);

    expect($row['status'])->toBe('skip')
        ->and($row['skip_reason'])->toBe('score_out_of_range');
});

it('breaks a created_at tie between applications by the higher id', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $student = makeMajorImportStudentWithId('STU2009');
    $tiedAt = now();
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'IELTS',
        'overall' => 5.0,
        'created_at' => $tiedAt,
    ]);
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'IELTS',
        'overall' => 8.0,
        'created_at' => $tiedAt,
    ]);

    // Same created_at for both — the higher (later-inserted) id must win.
    $row = $mapper->mapRow(['STU2009'], 2, 0, $student->campus_id);

    expect($row['overall_score'])->toBe(8.0);
});

it('sets overall_score on a below_threshold_excluded row so staff can see the actual number', function (): void {
    $mapper = new BulkMajorPlacementRowMapper;
    $student = makeMajorImportStudentWithId('STU2007');
    StudentApplication::factory()->create([
        'student_id' => $student->id,
        'english_test_type' => 'IELTS',
        'overall' => 4.5,
    ]);

    $row = $mapper->mapRow(['STU2007'], 2, 0, $student->campus_id);

    expect($row['skip_reason'])->toBe('below_threshold_excluded')
        ->and($row['overall_score'])->toBe(4.5);
});
