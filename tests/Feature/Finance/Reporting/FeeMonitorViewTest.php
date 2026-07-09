<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Batch/helpers.php';

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();

    session([
        'current_campus_id' => $this->campus->id,
        'current_semester_id' => $this->semester->id,
    ]);
});

it('renders fee monitor props with summary, filters, campus scope, and ACAD-RET gate metadata', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = makeBatchHpStudent($this->campus, $this->semester, 'FM-001');

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'fee-monitor']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Reporting/Index', false)
            ->where('active_view', 'fee-monitor')
            ->where('views.0.key', 'fee-monitor')
            ->where('views.0.status', 'implemented')
            ->has('fee_monitor.rows')
            ->has('fee_monitor.summary')
            ->has('fee_monitor.filters')
            ->has('fee_monitor.filter_options')
            ->where('fee_monitor.meta.acad_ret_gate.missing_inference_enabled', true)
            ->where('fee_monitor.meta.acad_ret_gate.excluded_missing_sources', [])
            ->where('fee_monitor.meta.semester_id', $this->semester->id)
            ->has('fee_monitor.computed_at')
        );

    expect($student->campus_id)->toBe($this->campus->id);
});

it('maps missing tuition rows and supports generation-state filtering', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $missingStudent = makeBatchHpStudent($this->campus, $this->semester, 'FM-MISSING');
    $generatedStudent = makeBatchHpStudent($this->campus, $this->semester, 'FM-GENERATED');
    seedBatchActiveCharge($generatedStudent, $this->semester, FinanceCharge::TYPE_TUITION_TERM);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', [
            'view' => 'fee-monitor',
            'generation_state' => 'missing',
            'search' => 'FM-MISSING',
        ]))
        ->assertOk();

    $rows = $response->original->getData()['page']['props']['fee_monitor']['rows']['data'];

    expect($rows)->not->toBeEmpty()
        ->and(collect($rows)->pluck('student.student_code'))->toContain('FM-MISSING')
        ->and(collect($rows)->pluck('student.student_code'))->not->toContain('FM-GENERATED')
        ->and(collect($rows)->every(fn (array $row) => $row['generation_state'] === 'missing'))->toBeTrue();
});

it('shows an existing retake charge with no Academic registration as generated, not missing', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = Student::factory()->forCampus($this->campus)->create([
        'student_id' => 'FM-RETAKE',
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 500000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => 'App\\Models\\CourseRetakeRegistration',
        'source_id' => 99,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', [
            'view' => 'fee-monitor',
            'expected_fee_type' => 'course_retake',
            'search' => 'FM-RETAKE',
        ]))
        ->assertOk();

    $rows = $response->original->getData()['page']['props']['fee_monitor']['rows']['data'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['generation_state'])->toBe('generated')
        ->and($rows[0]['expected_source'])->toBe('course_retake')
        ->and($rows[0]['drilldowns']['lookup_charge_id'])->not->toBeNull();
});

it('exposes drilldown links for generated charge rows', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = makeBatchHpStudent($this->campus, $this->semester, 'FM-DRILL');
    $charge = seedBatchActiveCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', [
            'view' => 'fee-monitor',
            'generation_state' => 'generated',
            'search' => 'FM-DRILL',
        ]))
        ->assertOk();

    $row = $response->original->getData()['page']['props']['fee_monitor']['rows']['data'][0];

    expect($row['drilldowns']['lookup_charge_id'])->toBe($charge->id)
        ->and($row['drilldowns']['student_360_focus'])->toBe('charge:'.$charge->id)
        ->and($row['batch_handoff'])->toBeNull();
});

it('does not report missing rows for non-mandatory fees (admission, BHYT)', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    // Intake semester = active semester → the admission-fee builder includes this
    // student, but they have no admission charge. Admission is NOT mandatory, so it
    // must not surface as a "missing" row (same rule applies to BHYT).
    Student::factory()->forCampus($this->campus)->create([
        'student_id' => 'FM-OPT',
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', [
            'view' => 'fee-monitor',
            'search' => 'FM-OPT',
        ]))
        ->assertOk();

    $rows = collect($response->original->getData()['page']['props']['fee_monitor']['rows']['data']);

    expect($rows->pluck('expected_source'))
        ->not->toContain('admission_enrollment')
        ->not->toContain('bhyt_health_insurance')
        ->and($rows->where('generation_state', 'missing'))->toBeEmpty();
});

it('hides intake_major from the student-status filter (HP lane still covers it)', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'fee-monitor']))
        ->assertOk();

    $statuses = collect($response->original->getData()['page']['props']['fee_monitor']['filter_options']['student_statuses'])
        ->pluck('value');

    expect($statuses)
        ->toContain('intake_pre_uni_gc')
        ->toContain('intake_course')
        ->not->toContain('intake_major');
});

it('excludes a deferred-enrollment student from missing tuition rows', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    // Billable HP student (would otherwise surface as a "missing tuition" row),
    // but their semester enrollment is deferred (registration_status = 'defer').
    // FIN-REV-020-02 (M2): a deferred enrollment is non-billable, so Fee-Monitor
    // must not flag it as missing/expected.
    $student = makeBatchHpStudent($this->campus, $this->semester, 'FM-DEFER');

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);
    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'defer',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', [
            'view' => 'fee-monitor',
            'generation_state' => 'missing',
            'search' => 'FM-DEFER',
        ]))
        ->assertOk();

    $rows = collect($response->original->getData()['page']['props']['fee_monitor']['rows']['data']);

    expect($rows->pluck('student.student_code'))->not->toContain('FM-DEFER');
});
