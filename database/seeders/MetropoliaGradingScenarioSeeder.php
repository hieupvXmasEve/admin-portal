<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Actions\MarkCourseOfferingCompletedAction;
use App\Modules\Academic\Delivery\Support\Grading\GradingCalculatorResolver;
use App\Modules\Academic\Delivery\Support\Grading\MetropoliaSchemeCatalog;
use App\Services\V1\Student\GradeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Manually-run, idempotent demo/test data provisioner for every Metropolia
 * grading scheme (17 catalog keys + 1 default-weighted control), each with a
 * syllabus template, a course offering, and a cast of students whose scores
 * exercise every display archetype (clear pass, clear fail, exact threshold
 * boundaries, gate failure, zero, ungraded). Most offerings are finalized
 * through the real {@see MarkCourseOfferingCompletedAction} so stored grade
 * breakdowns are production-true; a couple are left unfinalized to exercise
 * the pre-finalize display.
 *
 * Not registered in {@see DatabaseSeeder} — run manually:
 *   ./scripts/dev.sh artisan db:seed --class="Database\Seeders\MetropoliaGradingScenarioSeeder"
 *
 * Never run with --env=testing: this project has no .env.testing, so that
 * flag would target the dev database destructively.
 */
class MetropoliaGradingScenarioSeeder extends Seeder
{
    /**
     * Left unfinalized to exercise the pre-finalize display (PRD issue 01).
     * Also excludes the two metropolia_v2 schemes whose formula has a
     * negative offset (e.g. `(ASSIGNMENT - 40) / 10`) — the calculator's
     * diagnostic final_percentage isn't clamped to 0-100 before storage, so
     * a zero/clear-fail archetype run through the real finalize path would
     * violate the academic_records check constraint. Fixing that is a
     * calculator change, which is out of scope for this seeder (PRD "Out of
     * Scope"); leaving these unfinalized sidesteps it entirely.
     */
    private const UNFINALIZED_SCHEME_KEYS = [
        'software_1.database',
        'hardware_1.health_technology',
        'hardware_2.cloud_computing',
    ];

    /** Short (<=7 char) natural-key fragments — student_id/national_id are capped at 20 chars. */
    private const SCHEME_CODES = [
        'software_1.programming' => 'SW1PROG',
        'software_1.database' => 'SW1DB',
        'software_1.maths_physics' => 'SW1MP',
        'software_1.project' => 'SW1PROJ',
        'software_2.programming' => 'SW2PROG',
        'software_2.web_development' => 'SW2WEB',
        'software_2.maths_physics' => 'SW2MP',
        'software_2.project' => 'SW2PROJ',
        'hardware_1.digital_systems' => 'HW1DS',
        'hardware_1.networking' => 'HW1NET',
        'hardware_1.linux' => 'HW1LNX',
        'hardware_1.health_technology' => 'HW1HT',
        'hardware_1.maths_physics' => 'HW1MP',
        'hardware_2.electronics' => 'HW2ELEC',
        'hardware_2.maths_physics' => 'HW2MP',
        'hardware_2.cloud_computing' => 'HW2CC',
        'hardware_2.project' => 'HW2PROJ',
    ];

    private Campus $campus;

    private Semester $semester;

    private Program $program;

    private CurriculumVersion $curriculumVersion;

    public function run(): void
    {
        $this->campus = Campus::firstOrCreate(
            ['code' => 'MET-SEED'],
            ['name' => 'Metropolia Grading Seed Campus', 'address' => 'Metropolia Seed Address']
        );

        // The finalize action checks the offering's campus against app('campus').
        app()->instance('campus', $this->campus);

        $this->semester = Semester::firstOrCreate(
            ['code' => 'MET-SEED-SEM'],
            [
                'name' => 'Metropolia Grading Seed Semester',
                'start_date' => now()->subMonths(6)->toDateString(),
                'end_date' => now()->subDay()->toDateString(),
                'is_active' => false,
            ]
        );

        $this->program = Program::firstOrCreate(
            ['code' => 'MET-SEED-PRG'],
            ['name' => 'Metropolia Grading Seed Program']
        );

        $this->curriculumVersion = CurriculumVersion::firstOrCreate(
            ['program_id' => $this->program->id, 'version_code' => 'MET-SEED-V1'],
            ['semester_id' => $this->semester->id]
        );

        $catalog = app(MetropoliaSchemeCatalog::class);

        foreach ($catalog->keys() as $key) {
            $this->seedSchemeOffering($key, $catalog->get($key));
        }

        $this->seedDefaultControlOffering();
    }

    /**
     * @param  array<string, mixed>  $scheme
     */
    private function seedSchemeOffering(string $key, array $scheme): void
    {
        $code = self::SCHEME_CODES[$key];

        $unit = Unit::firstOrCreate(
            ['code' => "MET-{$code}"],
            [
                'name' => 'Metropolia Seed - '.($scheme['source_reference'] ?? $key),
                'credit_points' => 3,
            ]
        );

        $this->seedCurriculumUnit($unit);

        $template = SyllabusTemplate::firstOrCreate(
            ['title' => "Metropolia Seed: {$key}"],
            [
                'unit_id' => $unit->id,
                'version' => '1.0',
                'min_grade_threshold' => 60,
                'min_attendance_threshold' => 0,
                'is_default' => false,
                'is_active' => true,
                'grading_scheme' => $scheme,
                'created_by' => null,
            ]
        );

        // Keep the stored scheme identical to the canonical pack on re-run.
        if ($template->grading_scheme !== $scheme) {
            $template->forceFill(['grading_scheme' => $scheme])->save();
        }

        $components = $this->seedComponents($template, $scheme['components']);

        $offering = $this->seedOffering($unit, $template);

        $firstStudent = null;

        foreach ($this->buildArchetypes($scheme) as $suffix => $scores) {
            $student = $this->seedStudent($code, $suffix);
            $firstStudent ??= $student;

            $this->registerStudent($student, $offering, $unit);

            if ($scores !== null) {
                $this->seedScores($student, $offering, $components, $scores);
            }
        }

        $this->ensureFinalizeBlockerSatisfied($offering, $firstStudent);

        if (! in_array($key, self::UNFINALIZED_SCHEME_KEYS, true) && $offering->fresh()->course_status !== 'completed') {
            MarkCourseOfferingCompletedAction::run($offering->fresh());
        }
    }

    private function seedDefaultControlOffering(): void
    {
        $unit = Unit::firstOrCreate(
            ['code' => 'MET-DEFCTL'],
            ['name' => 'Metropolia Seed - Default Weighted Control', 'credit_points' => 3]
        );

        $this->seedCurriculumUnit($unit);

        $template = SyllabusTemplate::firstOrCreate(
            ['title' => 'Metropolia Seed: default_weighted_control'],
            [
                'unit_id' => $unit->id,
                'version' => '1.0',
                'min_grade_threshold' => 60,
                'min_attendance_threshold' => 0,
                'is_default' => false,
                'is_active' => true,
                'grading_scheme' => null,
                'created_by' => null,
            ]
        );

        $components = $this->seedComponents($template, [
            ['code' => 'ASSIGNMENT', 'label' => 'Assignments'],
            ['code' => 'EXAM', 'label' => 'Final Exam'],
        ]);

        $offering = $this->seedOffering($unit, $template);

        $archetypes = [
            'PASS' => ['ASSIGNMENT' => 95.0, 'EXAM' => 95.0],
            'BOUND' => ['ASSIGNMENT' => 60.0, 'EXAM' => 60.0],
            'FAIL' => ['ASSIGNMENT' => 30.0, 'EXAM' => 30.0],
            'ZERO' => ['ASSIGNMENT' => 0.0, 'EXAM' => 0.0],
            'UNG' => null,
        ];

        $firstStudent = null;

        foreach ($archetypes as $suffix => $scores) {
            $student = $this->seedStudent('DEFCTL', $suffix);
            $firstStudent ??= $student;

            $this->registerStudent($student, $offering, $unit);

            if ($scores !== null) {
                $this->seedScores($student, $offering, $components, $scores);
            }
        }

        $this->ensureFinalizeBlockerSatisfied($offering, $firstStudent);

        if ($offering->fresh()->course_status !== 'completed') {
            MarkCourseOfferingCompletedAction::run($offering->fresh());
        }
    }

    /**
     * Link the seeded unit into the seed curriculum version so
     * {@see GradeService::getStudentGrades()} (which
     * matches academic records against `curriculumVersion->curriculumUnits()`
     * by `unit_id`) surfaces these offerings on the student portal's
     * semester overview page, not just the per-course detail endpoint.
     */
    private function seedCurriculumUnit(Unit $unit): void
    {
        CurriculumUnit::firstOrCreate(
            [
                'curriculum_version_id' => $this->curriculumVersion->id,
                'unit_id' => $unit->id,
            ],
            [
                'semester_id' => $this->semester->id,
                'unit_scope' => 'common',
                'year_level' => 1,
                'semester_number' => 1,
            ]
        );
    }

    private function seedOffering(Unit $unit, SyllabusTemplate $template): CourseOffering
    {
        $offering = CourseOffering::firstOrCreate(
            [
                'unit_id' => $unit->id,
                'semester_id' => $this->semester->id,
                'section_code' => 'SEED',
                'campus_id' => $this->campus->id,
            ],
            [
                'syllabus_template_id' => $template->id,
                'max_capacity' => 50,
                'delivery_mode' => 'in_person',
                'enrollment_status' => 'closed',
                'course_status' => 'in_progress',
                'is_canvas_synced' => false,
                'grading_type' => 'grade',
            ]
        );

        if ($offering->syllabus_template_id !== $template->id) {
            $offering->forceFill(['syllabus_template_id' => $template->id])->save();
        }

        return $offering;
    }

    /**
     * @param  array<int, array<string, mixed>>  $schemeComponents
     * @return array<string, array{component: AssessmentComponent, detail: AssessmentComponentDetail}>
     */
    private function seedComponents(SyllabusTemplate $template, array $schemeComponents): array
    {
        $weight = round(100 / max(count($schemeComponents), 1), 2);
        $components = [];

        foreach ($schemeComponents as $schemeComponent) {
            $code = $schemeComponent['code'];
            $label = $schemeComponent['label'] ?? $code;

            $component = AssessmentComponent::firstOrCreate(
                ['syllabus_template_id' => $template->id, 'code' => $code],
                [
                    'name' => $label,
                    'weight' => $weight,
                    'type' => Str::contains(Str::lower($code), 'exam') ? 'exam' : 'assignment',
                    'is_required_to_sit_final_exam' => false,
                    'status' => 'published',
                    'is_published' => true,
                ]
            );

            $detail = AssessmentComponentDetail::firstOrCreate(
                ['assessment_component_id' => $component->id, 'name' => 'Score'],
                ['weight' => 1]
            );

            $components[$code] = ['component' => $component, 'detail' => $detail];
        }

        return $components;
    }

    private function registerStudent(Student $student, CourseOffering $offering, Unit $unit): void
    {
        $registration = CourseRegistration::firstOrCreate(
            ['student_id' => $student->id, 'course_offering_id' => $offering->id],
            [
                'semester_id' => $offering->semester_id,
                'registration_status' => 'confirmed',
                'registration_date' => now(),
                'registration_method' => 'admin_override',
                'credit_hours' => $unit->credit_points ?? 3,
            ]
        );

        if ($registration->wasRecentlyCreated) {
            $offering->increment('current_enrollment');
        }

        AcademicRecord::firstOrCreate(
            ['student_id' => $student->id, 'course_offering_id' => $offering->id],
            [
                'semester_id' => $offering->semester_id,
                'unit_id' => $unit->id,
                'program_id' => $this->program->id,
                'campus_id' => $this->campus->id,
                'grade_status' => 'in_progress',
                'completion_status' => 'in_progress',
                'enrollment_date' => now(),
                'credit_hours' => $unit->credit_points ?? 3,
                'credit_hours_earned' => 0,
                'credit_points' => $unit->credit_points ?? 3,
                'credit_points_earned' => 0,
            ]
        );
    }

    /**
     * @param  array<string, array{component: AssessmentComponent, detail: AssessmentComponentDetail}>  $components
     * @param  array<string, float>  $scores
     */
    private function seedScores(Student $student, CourseOffering $offering, array $components, array $scores): void
    {
        foreach ($scores as $code => $percentage) {
            $detail = $components[$code]['detail'] ?? null;

            if (! $detail) {
                continue;
            }

            AssessmentComponentDetailScore::updateOrCreate(
                [
                    'assessment_component_detail_id' => $detail->id,
                    'student_id' => $student->id,
                    'course_offering_id' => $offering->id,
                ],
                [
                    'percentage_score' => $percentage,
                    'status' => 'graded',
                    'score_status' => 'final',
                    'score_excluded' => false,
                    'graded_at' => now(),
                ]
            );
        }
    }

    /**
     * One completed session with manually-confirmed attendance — the minimum
     * {@see MarkCourseOfferingCompletedAction} requires before it will finalize.
     */
    private function ensureFinalizeBlockerSatisfied(CourseOffering $offering, ?Student $attendee): void
    {
        if (! $attendee) {
            return;
        }

        $session = ClassSession::firstOrCreate(
            ['course_offering_id' => $offering->id, 'session_title' => 'Metropolia Seed Session'],
            [
                'session_date' => now()->subMonths(2)->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'session_type' => 'lecture',
                'delivery_mode' => 'in_person',
                'status' => 'completed',
            ]
        );

        Attendance::firstOrCreate(
            ['class_session_id' => $session->id, 'student_id' => $attendee->id],
            ['status' => 'present', 'recording_method' => 'manual']
        );
    }

    private function seedStudent(string $schemeCode, string $suffix): Student
    {
        $externalId = "M-{$schemeCode}-{$suffix}";

        return Student::firstOrCreate(
            ['student_id' => $externalId],
            [
                'full_name' => "Metropolia Seed ({$schemeCode} {$suffix})",
                'email' => Str::lower(str_replace(['-', ' '], ['.', '.'], $externalId)).'@metropolia-seed.test',
                'phone' => '0000000000',
                'date_of_birth' => '2000-01-01',
                'gender' => 'other',
                'nationality' => 'vietnamese',
                'national_id' => $externalId,
                'address' => 'Metropolia Seed Address',
                'campus_id' => $this->campus->id,
                'program_id' => $this->program->id,
                'curriculum_version_id' => $this->curriculumVersion->id,
                'admission_date' => now()->subYear()->toDateString(),
                'status' => 'intake_course',
                'academic_status' => 'active',
                'intake' => 1,
                'intake_mode' => 'sequential',
                'intake_semester_id' => $this->semester->id,
            ]
        );
    }

    /**
     * Builds the archetype cast for a scheme: clear pass, clear fail, one
     * boundary student per distinct threshold the scheme declares, a
     * gate-failure student (only when the scheme declares a requirement),
     * a zero-score student, and an ungraded student (null scores => no
     * AssessmentComponentDetailScore rows are created for them).
     *
     * @param  array<string, mixed>  $scheme
     * @return array<string, array<string, float>|null>
     */
    private function buildArchetypes(array $scheme): array
    {
        $codes = array_column($scheme['components'], 'code');
        $allAt = fn (float $pct): array => array_fill_keys($codes, $pct);

        $archetypes = [
            // Some catalog schemes sum multiple uncapped component conversions
            // (e.g. direct + linear) past their nominal 0-5 scale, which would
            // overflow the diagnostic 0-100 final_percentage if every
            // component were simply maxed — so PASS uses the highest
            // percentage the real calculator confirms stays in bounds.
            'PASS' => $allAt($this->safePassPercentage($scheme, $codes)),
            'FAIL' => $allAt(10.0),
            'ZERO' => $allAt(0.0),
        ];

        // Boundary archetypes isolate one component at its exact threshold and
        // zero every other component, so the archetype demonstrates that one
        // threshold's effect without risking the same multi-component overflow.
        foreach ($this->collectThresholds($scheme) as $index => [$code, $threshold]) {
            $scores = array_fill_keys($codes, 0.0);
            $scores[$code] = $threshold;
            $archetypes['B'.($index + 1)] = $scores;
        }

        $gatedCode = $this->firstGatedCode($scheme);
        if ($gatedCode !== null) {
            $failing = $allAt(95.0);
            $failing[$gatedCode] = max(0.0, $this->gateThresholdFor($scheme, $gatedCode) - 10.0);
            $archetypes['GATE'] = $failing;
        }

        $archetypes['UNG'] = null;

        return $archetypes;
    }

    /**
     * Highest percentage (100 down to 60, in steps of 5) that the real
     * calculator confirms both passes and keeps final_percentage within the
     * academic_records 0-100 check constraint when applied to every
     * component uniformly.
     *
     * @param  array<string, mixed>  $scheme
     * @param  array<int, string>  $codes
     */
    private function safePassPercentage(array $scheme, array $codes): float
    {
        $calculator = app(GradingCalculatorResolver::class)->resolve($scheme);

        for ($pct = 100; $pct >= 60; $pct -= 5) {
            $result = $calculator->calculate(array_fill_keys($codes, (float) $pct), $scheme);
            $withinBounds = $result->finalPercentage === null
                || ($result->finalPercentage >= 0.0 && $result->finalPercentage <= 100.0);

            if ($withinBounds && $result->passed) {
                return (float) $pct;
            }
        }

        return 60.0;
    }

    /**
     * @param  array<string, mixed>  $scheme
     * @return array<int, array{0: string, 1: float}> component code => threshold value
     */
    private function collectThresholds(array $scheme): array
    {
        $thresholds = [];

        if (($scheme['engine'] ?? null) === 'metropolia_v2') {
            foreach ($scheme['pass_requirements'] ?? [] as $requirement) {
                $thresholds[] = [$requirement['code'], (float) $requirement['min_pct']];
            }

            return $this->dedupeThresholdValues($thresholds);
        }

        foreach ($scheme['components'] as $component) {
            $code = $component['code'];

            if (isset($component['gate']['min_pct'])) {
                $thresholds[] = [$code, (float) $component['gate']['min_pct']];
            }

            $conversion = $component['conversion'] ?? null;

            match ($conversion['type'] ?? null) {
                'linear' => $thresholds = [...$thresholds, [$code, (float) $conversion['min_pct']], [$code, (float) $conversion['max_pct']]],
                'threshold' => $thresholds = [...$thresholds, ...array_map(fn ($step) => [$code, (float) $step['min_pct']], $conversion['steps'])],
                'pass_fail' => $thresholds[] = [$code, (float) $conversion['min_pct']],
                default => null,
            };
        }

        return $this->dedupeThresholdValues($thresholds);
    }

    /**
     * @param  array<int, array{0: string, 1: float}>  $thresholds
     * @return array<int, array{0: string, 1: float}>
     */
    private function dedupeThresholdValues(array $thresholds): array
    {
        $seen = [];
        $unique = [];

        foreach ($thresholds as $threshold) {
            if (in_array($threshold[1], $seen, true)) {
                continue;
            }

            $seen[] = $threshold[1];
            $unique[] = $threshold;
        }

        return $unique;
    }

    /**
     * @param  array<string, mixed>  $scheme
     */
    private function firstGatedCode(array $scheme): ?string
    {
        if (($scheme['engine'] ?? null) === 'metropolia_v2') {
            return $scheme['pass_requirements'][0]['code'] ?? null;
        }

        foreach ($scheme['components'] as $component) {
            if (isset($component['gate'])) {
                return $component['code'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $scheme
     */
    private function gateThresholdFor(array $scheme, string $code): float
    {
        if (($scheme['engine'] ?? null) === 'metropolia_v2') {
            foreach ($scheme['pass_requirements'] ?? [] as $requirement) {
                if ($requirement['code'] === $code) {
                    return (float) $requirement['min_pct'];
                }
            }
        }

        foreach ($scheme['components'] as $component) {
            if ($component['code'] === $code) {
                return (float) $component['gate']['min_pct'];
            }
        }

        return 40.0;
    }
}
