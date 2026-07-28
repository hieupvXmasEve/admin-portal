<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Support\Grading\GradingSchemeValidator;
use App\Modules\Academic\Delivery\Support\Grading\MetropoliaSchemeCatalog;
use Database\Seeders\MetropoliaGradingScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

function metropoliaSeededTemplates(): Collection
{
    return SyllabusTemplate::where('title', 'like', 'Metropolia Seed:%')->get();
}

it('provisions every catalog scheme template plus the default control, each with an offering and enrolled students', function () {
    $this->seed(MetropoliaGradingScenarioSeeder::class);

    $catalog = app(MetropoliaSchemeCatalog::class);
    $templates = metropoliaSeededTemplates();

    // 17 catalog schemes + 1 default-weighted control.
    expect($templates)->toHaveCount(18);

    foreach ($catalog->keys() as $key) {
        $template = $templates->firstWhere('title', "Metropolia Seed: {$key}");
        expect($template)->not->toBeNull()
            ->and($template->grading_scheme)->toBe($catalog->get($key));

        $unit = Unit::find($template->unit_id);
        $offering = CourseOffering::where('unit_id', $unit->id)->first();
        expect($offering)->not->toBeNull()
            ->and($offering->syllabus_template_id)->toBe($template->id);

        // Every offering seeds at minimum a clear pass, clear fail, zero, and ungraded student.
        $registeredCount = CourseRegistration::where('course_offering_id', $offering->id)->count();
        expect($registeredCount)->toBeGreaterThanOrEqual(4);
    }
});

it('finalizes the majority of scheme offerings with a grade breakdown that passes the real scheme validator', function () {
    $this->seed(MetropoliaGradingScenarioSeeder::class);

    $validator = app(GradingSchemeValidator::class);
    $templates = metropoliaSeededTemplates()->whereNotNull('grading_scheme');

    $finalizedCount = 0;
    $unfinalizedCount = 0;

    foreach ($templates as $template) {
        expect($validator->validate($template->grading_scheme))->toBe([]);

        $unit = Unit::find($template->unit_id);
        $offering = CourseOffering::where('unit_id', $unit->id)->firstOrFail();

        if ($offering->course_status !== 'completed') {
            $unfinalizedCount++;

            continue;
        }

        $finalizedCount++;

        $records = AcademicRecord::where('course_offering_id', $offering->id)->get();
        expect($records)->not->toBeEmpty();

        foreach ($records as $record) {
            expect($record->final_letter_grade)->not->toBeNull()
                ->and($record->grade_breakdown)->toBeArray()
                ->and($record->grade_breakdown['engine'])->toBe($template->grading_scheme['engine']);
        }
    }

    expect($finalizedCount)->toBeGreaterThan($unfinalizedCount)
        ->and($unfinalizedCount)->toBeGreaterThanOrEqual(2);
});

it('leaves the default-weighted control offering scheme-free and finalizes it through the default path', function () {
    $this->seed(MetropoliaGradingScenarioSeeder::class);

    $template = SyllabusTemplate::where('title', 'Metropolia Seed: default_weighted_control')->firstOrFail();
    expect($template->grading_scheme)->toBeNull();

    $offering = CourseOffering::where('unit_id', $template->unit_id)->firstOrFail();
    expect($offering->course_status)->toBe('completed');

    $records = AcademicRecord::where('course_offering_id', $offering->id)
        ->whereNotNull('grade_breakdown')
        ->get();

    expect($records)->not->toBeEmpty();

    foreach ($records as $record) {
        expect($record->grade_breakdown['engine'])->toBe('default_weighted_percentage');
    }
});

it('is idempotent across repeated runs', function () {
    $this->seed(MetropoliaGradingScenarioSeeder::class);

    $countsAfterFirstRun = [
        'units' => Unit::count(),
        'templates' => SyllabusTemplate::count(),
        'offerings' => CourseOffering::count(),
        'students' => Student::count(),
        'registrations' => CourseRegistration::count(),
        'records' => AcademicRecord::count(),
    ];

    $this->seed(MetropoliaGradingScenarioSeeder::class);

    $countsAfterSecondRun = [
        'units' => Unit::count(),
        'templates' => SyllabusTemplate::count(),
        'offerings' => CourseOffering::count(),
        'students' => Student::count(),
        'registrations' => CourseRegistration::count(),
        'records' => AcademicRecord::count(),
    ];

    expect($countsAfterSecondRun)->toBe($countsAfterFirstRun);
});

it('demonstrates the gate-failure and threshold-boundary archetypes with real grade_breakdown data', function () {
    $this->seed(MetropoliaGradingScenarioSeeder::class);

    // software_1.programming: ASSIGNMENT gate 40, EXAM gate 40 + linear 40->88.
    $unit = Unit::where('code', 'MET-SW1PROG')->firstOrFail();
    $offering = CourseOffering::where('unit_id', $unit->id)->firstOrFail();

    $gateStudent = Student::where('student_id', 'M-SW1PROG-GATE')->firstOrFail();
    $gateRecord = AcademicRecord::where('course_offering_id', $offering->id)
        ->where('student_id', $gateStudent->id)
        ->firstOrFail();

    expect($gateRecord->grade_breakdown['gates_passed'])->toBeFalse()
        ->and($gateRecord->grade_breakdown['gate_failures'])->not->toBeEmpty()
        ->and($gateRecord->final_letter_grade)->toBe('0');

    // B2 isolates EXAM at its 88% max_pct boundary (other components zeroed).
    $boundaryStudent = Student::where('student_id', 'M-SW1PROG-B2')->firstOrFail();
    $boundaryRecord = AcademicRecord::where('course_offering_id', $offering->id)
        ->where('student_id', $boundaryStudent->id)
        ->firstOrFail();

    expect((float) $boundaryRecord->grade_breakdown['components']['EXAM']['converted_grade'])->toBe(5.0);
});
