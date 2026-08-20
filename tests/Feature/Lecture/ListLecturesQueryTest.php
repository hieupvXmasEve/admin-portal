<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Unit;
use App\Queries\Lecture\ListLecturesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters lecturers by semester and EGC unit type within the current campus', function () {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $semesterOne = Semester::factory()->create(['name' => 'Semester 1']);
    $semesterTwo = Semester::factory()->create(['name' => 'Semester 2']);
    $egcUnit = Unit::factory()->create(['code' => 'EGC100', 'unit_type' => 'egc']);
    $aiUnit = Unit::factory()->create(['code' => 'AI100', 'unit_type' => 'ai']);

    $currentEgcLecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'first_name' => 'Current',
        'last_name' => 'EGC',
    ]);
    $oldEgcLecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'first_name' => 'Old',
        'last_name' => 'EGC',
    ]);
    $currentAiLecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'first_name' => 'Current',
        'last_name' => 'AI',
    ]);
    $otherCampusEgcLecturer = Lecture::factory()->create([
        'campus_id' => $otherCampus->id,
        'first_name' => 'Other',
        'last_name' => 'Campus',
    ]);

    $currentEgcOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semesterTwo->id,
        'unit_id' => $egcUnit->id,
        'lecture_id' => $currentEgcLecturer->id,
    ]);
    $oldEgcOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semesterOne->id,
        'unit_id' => $egcUnit->id,
        'lecture_id' => $oldEgcLecturer->id,
    ]);
    $currentAiOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semesterTwo->id,
        'unit_id' => $aiUnit->id,
        'lecture_id' => $currentAiLecturer->id,
    ]);
    $otherCampusEgcOffering = CourseOffering::factory()->create([
        'campus_id' => $otherCampus->id,
        'semester_id' => $semesterTwo->id,
        'unit_id' => $egcUnit->id,
        'lecture_id' => $otherCampusEgcLecturer->id,
    ]);

    // Teaching load is decided by who actually teaches sessions, not by the
    // offering's default lecture_id column.
    ClassSession::factory()->create(['course_offering_id' => $currentEgcOffering->id, 'lecture_id' => $currentEgcLecturer->id]);
    ClassSession::factory()->create(['course_offering_id' => $oldEgcOffering->id, 'lecture_id' => $oldEgcLecturer->id]);
    ClassSession::factory()->create(['course_offering_id' => $currentAiOffering->id, 'lecture_id' => $currentAiLecturer->id]);
    ClassSession::factory()->create(['course_offering_id' => $otherCampusEgcOffering->id, 'lecture_id' => $otherCampusEgcLecturer->id]);

    $result = (new ListLecturesQuery)->handle([
        'semester_id' => (string) $semesterTwo->id,
        'unit_type' => 'egc',
        'sort' => 'full_name',
        'direction' => 'asc',
        'per_page' => 15,
    ], $campus->id);

    $ids = collect($result->items())->pluck('id');

    expect($ids)
        ->toContain($currentEgcLecturer->id)
        ->not->toContain($oldEgcLecturer->id)
        ->not->toContain($currentAiLecturer->id)
        ->not->toContain($otherCampusEgcLecturer->id);
});

it('can show all historical EGC lecturers when the semester filter is cleared', function () {
    $campus = Campus::factory()->create();
    $semesterOne = Semester::factory()->create(['name' => 'Semester 1']);
    $semesterTwo = Semester::factory()->create(['name' => 'Semester 2']);
    $egcUnit = Unit::factory()->create(['code' => 'EGC100', 'unit_type' => 'egc']);
    $aiUnit = Unit::factory()->create(['code' => 'AI100', 'unit_type' => 'ai']);

    $semesterOneLecturer = Lecture::factory()->create(['campus_id' => $campus->id]);
    $semesterTwoLecturer = Lecture::factory()->create(['campus_id' => $campus->id]);
    $aiLecturer = Lecture::factory()->create(['campus_id' => $campus->id]);

    $semesterOneOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semesterOne->id,
        'unit_id' => $egcUnit->id,
        'lecture_id' => $semesterOneLecturer->id,
    ]);
    $semesterTwoOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semesterTwo->id,
        'unit_id' => $egcUnit->id,
        'lecture_id' => $semesterTwoLecturer->id,
    ]);
    $aiOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semesterTwo->id,
        'unit_id' => $aiUnit->id,
        'lecture_id' => $aiLecturer->id,
    ]);

    ClassSession::factory()->create(['course_offering_id' => $semesterOneOffering->id, 'lecture_id' => $semesterOneLecturer->id]);
    ClassSession::factory()->create(['course_offering_id' => $semesterTwoOffering->id, 'lecture_id' => $semesterTwoLecturer->id]);
    ClassSession::factory()->create(['course_offering_id' => $aiOffering->id, 'lecture_id' => $aiLecturer->id]);

    $result = (new ListLecturesQuery)->handle([
        'semester_id' => 'all',
        'unit_type' => 'egc',
        'sort' => 'full_name',
        'direction' => 'asc',
        'per_page' => 15,
    ], $campus->id);

    $ids = collect($result->items())->pluck('id');

    expect($ids)
        ->toContain($semesterOneLecturer->id)
        ->toContain($semesterTwoLecturer->id)
        ->not->toContain($aiLecturer->id);
});

it('surfaces a lecturer who only teaches some sessions of an offering, not just the offering primary lecturer', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create(['unit_type' => 'egc']);

    $primaryLecturer = Lecture::factory()->create(['campus_id' => $campus->id]);
    $coTeachingLecturer = Lecture::factory()->create(['campus_id' => $campus->id]);

    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'lecture_id' => $primaryLecturer->id,
    ]);

    ClassSession::factory()->create(['course_offering_id' => $offering->id, 'lecture_id' => $primaryLecturer->id]);
    ClassSession::factory()->create(['course_offering_id' => $offering->id, 'lecture_id' => $coTeachingLecturer->id]);

    $result = (new ListLecturesQuery)->handle([
        'semester_id' => (string) $semester->id,
        'sort' => 'full_name',
        'direction' => 'asc',
        'per_page' => 15,
    ], $campus->id);

    $ids = collect($result->items())->pluck('id');

    expect($ids)
        ->toContain($primaryLecturer->id)
        ->toContain($coTeachingLecturer->id);
});

it('excludes a course offering with no scheduled sessions from the semester filter, even with a primary lecture_id set', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $unassignedLecturer = Lecture::factory()->create(['campus_id' => $campus->id]);

    CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => $unassignedLecturer->id,
    ]);

    $result = (new ListLecturesQuery)->handle([
        'semester_id' => (string) $semester->id,
        'sort' => 'full_name',
        'direction' => 'asc',
        'per_page' => 15,
    ], $campus->id);

    $ids = collect($result->items())->pluck('id');

    expect($ids)->not->toContain($unassignedLecturer->id);
});
