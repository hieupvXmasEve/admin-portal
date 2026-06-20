<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\LinkLegacyRetakeDngToChargeAction;
use App\Modules\Academic\Actions\ReconcileLegacyRetakeFeesAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
});

function retakeLaneRecord(Unit $unit, array $overrides = []): AcademicRecord
{
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => test()->campus->id,
    ]);

    return AcademicRecord::factory()->create(array_merge([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED,
    ], $overrides));
}

function legacyRetakeCharge(array $opts = []): FinanceCharge
{
    return app(CreateFinanceChargeAction::class)->handle([
        'student_id' => test()->student->id,
        'semester_id' => test()->semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => $opts['amount'] ?? 3_000_000,
        'description' => $opts['description'] ?? 'Phí học lại (legacy import)',
    ]);
}

function paidHlDng(array $opts = []): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => test()->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU'.test()->student->id,
        'item_id' => $opts['item_id'] ?? 'HL-'.test()->student->id,
        'fee_type' => 'HL',
        'description' => $opts['description'] ?? 'Học phí học lại kỳ SUMMER 2026',
        'semester_id' => test()->semester->id,
        'amount' => $opts['amount'] ?? 3_000_000,
        'status' => $opts['status'] ?? DngPaymentRequest::STATUS_PAID_INVOICED,
        'finance_charge_id' => $opts['finance_charge_id'] ?? null,
    ]);
}

it('links a paid HL DNG request to an existing retake registration charge', function () {
    $unit = Unit::factory()->create(['retake_fee' => 3_000_000]);
    retakeLaneRecord($unit);
    $charge = legacyRetakeCharge();

    $registration = CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => AcademicRecord::query()->where('student_id', $this->student->id)->value('id'),
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
        'attempt_number' => 2,
        'retake_fee' => 3_000_000,
        'finance_charge_id' => $charge->id,
    ]);

    $dng = paidHlDng();

    $result = app(LinkLegacyRetakeDngToChargeAction::class)->run();

    expect($result['linked'])->toBe(1)
        ->and($result['paid_synced'])->toBe(1);

    $dng->refresh();
    $registration->refresh();

    expect($dng->finance_charge_id)->toBe($charge->id)
        ->and($registration->status)->toBe(CourseRetakeRegistration::STATUS_PAID);
});

it('reconciles a legacy retake_fee charge into a CourseRetakeRegistration source', function () {
    $unit = Unit::factory()->create(['code' => 'VOV001', 'retake_fee' => 3_000_000]);
    $record = retakeLaneRecord($unit);
    $charge = legacyRetakeCharge(['description' => 'Vovinam Retake Fee']);

    $result = app(ReconcileLegacyRetakeFeesAction::class)->run();

    $registration = CourseRetakeRegistration::query()->where('finance_charge_id', $charge->id)->first();

    expect($result['reconciled'])->toBe(1)
        ->and($registration)->not->toBeNull()
        ->and($registration->original_academic_record_id)->toBe($record->id)
        ->and($registration->policy_snapshot['legacy_backfill'] ?? null)->toBeTrue();

    $charge->refresh();
    expect($charge->source_type)->toBe(CourseRetakeRegistration::class)
        ->and($charge->source_id)->toBe($registration->id);
});

it('reports duplicate legacy retake charges when a registration already owns another charge', function () {
    $unit = Unit::factory()->create(['retake_fee' => 3_000_000]);
    retakeLaneRecord($unit);
    $existing = legacyRetakeCharge();

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => AcademicRecord::query()->where('student_id', $this->student->id)->value('id'),
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'status' => CourseRetakeRegistration::STATUS_ENROLLED,
        'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_PAID,
        'attempt_number' => 2,
        'retake_fee' => 3_000_000,
        'finance_charge_id' => $existing->id,
    ]);

    legacyRetakeCharge();

    $result = app(ReconcileLegacyRetakeFeesAction::class)->run();

    expect($result['exceptions'])->toBe(1)
        ->and($result['reconciled'])->toBe(0);

    $exception = collect($result['details'])->firstWhere('status', 'exception');
    expect($exception['reason'])->toBe(ReconcileLegacyRetakeFeesAction::REASON_DUPLICATE_CHARGE);
});

it('runs legacy retake reconciliation through the artisan command', function () {
    $unit = Unit::factory()->create(['retake_fee' => 3_000_000]);
    retakeLaneRecord($unit);
    $charge = legacyRetakeCharge();
    paidHlDng();

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => AcademicRecord::query()->where('student_id', $this->student->id)->value('id'),
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
        'attempt_number' => 2,
        'retake_fee' => 3_000_000,
        'finance_charge_id' => $charge->id,
    ]);

    $this->artisan('academic:reconcile-legacy-retake-fees')
        ->assertExitCode(0);

    expect(DngPaymentRequest::query()->whereNotNull('finance_charge_id')->count())->toBe(1);
});