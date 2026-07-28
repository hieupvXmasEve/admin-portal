<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Shared\Support\Academic\CourseGradeScale;

it('maps each percentage band to its letter grade', function (): void {
    expect(CourseGradeScale::letterGrade(100))->toBe('A+')
        ->and(CourseGradeScale::letterGrade(90))->toBe('A+')
        ->and(CourseGradeScale::letterGrade(89.99))->toBe('A')
        ->and(CourseGradeScale::letterGrade(85))->toBe('A')
        ->and(CourseGradeScale::letterGrade(80))->toBe('A-')
        ->and(CourseGradeScale::letterGrade(75))->toBe('B+')
        ->and(CourseGradeScale::letterGrade(70))->toBe('B')
        ->and(CourseGradeScale::letterGrade(65))->toBe('B-')
        ->and(CourseGradeScale::letterGrade(60))->toBe('C+')
        ->and(CourseGradeScale::letterGrade(55))->toBe('C')
        ->and(CourseGradeScale::letterGrade(50))->toBe('C-')
        ->and(CourseGradeScale::letterGrade(49.99))->toBe('F')
        ->and(CourseGradeScale::letterGrade(0))->toBe('F');
});

it('clamps grade points to the 4.0 scale', function (): void {
    expect(CourseGradeScale::gradePoints(100))->toBe(4.0)
        ->and(CourseGradeScale::gradePoints(120))->toBe(4.0)
        ->and(CourseGradeScale::gradePoints(75))->toBe(3.0)
        ->and(CourseGradeScale::gradePoints(50))->toBe(2.0)
        ->and(CourseGradeScale::gradePoints(0))->toBe(0.0)
        ->and(CourseGradeScale::gradePoints(-10))->toBe(0.0);
});

it('keeps the record model delegating to the same scale', function (): void {
    expect(AcademicRecord::calculateLetterGrade(72.5))
        ->toBe(CourseGradeScale::letterGrade(72.5))
        ->and(AcademicRecord::calculateGradePoints(72.5))
        ->toBe(CourseGradeScale::gradePoints(72.5))
        ->and(AcademicRecord::FAILURE_REASONS)
        ->toBe(CourseGradeScale::FAILURE_REASONS);
});
