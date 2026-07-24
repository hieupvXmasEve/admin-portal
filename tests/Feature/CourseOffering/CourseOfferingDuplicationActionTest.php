<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Actions\DuplicateCourseOfferingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('duplicates an offering through Delivery without its instructor or enrollment', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $instructor = Lecture::factory()->create(['campus_id' => $campus->id]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'lecture_id' => $instructor->id,
        'section_code' => 'A',
        'current_enrollment' => 12,
        'current_waitlist' => 4,
    ]);

    $duplicate = DuplicateCourseOfferingAction::run([
        'course_offering_id' => (int) $offering->id,
        'campus_id' => (int) $campus->id,
    ]);

    expect($duplicate->id)->not->toBe($offering->id)
        ->and($duplicate->lecture_id)->toBeNull()
        ->and($duplicate->current_enrollment)->toBe(0)
        ->and($duplicate->current_waitlist)->toBe(0)
        ->and($duplicate->section_code)->toBe('A_copy')
        ->and($duplicate->unit_id)->toBe($offering->unit_id)
        ->and($duplicate->semester_id)->toBe($offering->semester_id);
});
