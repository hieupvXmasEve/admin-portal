<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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

function makeLegacyBackfillRetakeSource(float $retakeFee = 1_500_000): CourseRetakeRegistration
{
    $unit = Unit::factory()->create();
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => test()->campus->id,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    return CourseRetakeRegistration::query()->create([
        'student_id' => test()->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => test()->semester->id,
        'campus_id' => test()->campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => $retakeFee,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
        'approved_by_user_id' => test()->user->id,
        'approved_at' => now(),
        'charge_created_by_user_id' => test()->user->id,
        'charge_created_at' => now(),
    ]);
}

function makeLegacyBackfillExamResitSource(float $feeAmount = 750_000): ExamResitAttempt
{
    $unit = Unit::factory()->create();
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => test()->campus->id,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
    ]);

    return ExamResitAttempt::query()->create([
        'student_id' => test()->student->id,
        'academic_record_id' => $record->id,
        'original_course_offering_id' => $courseOffering->id,
        'unit_id' => $unit->id,
        'campus_id' => test()->campus->id,
        'original_semester_id' => test()->semester->id,
        'operation_semester_id' => test()->semester->id,
        'charge_semester_id' => test()->semester->id,
        'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
        'status' => ExamResitAttempt::STATUS_APPROVED,
        'request_sequence' => 1,
        'approved_at' => now(),
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'fee_amount' => $feeAmount,
        'exam_resit_fee_snapshot' => $feeAmount,
        'charge_created_by_user_id' => test()->user->id,
        'charge_created_at' => now(),
    ]);
}

function makeLegacyBackfillCharge(
    string $chargeType,
    float $amount,
    string $status,
    ?string $sourceType,
    ?int $sourceId,
): FinanceCharge {
    return FinanceCharge::query()->create([
        'student_id' => test()->student->id,
        'semester_id' => test()->semester->id,
        'charge_type' => $chargeType,
        'amount' => $amount,
        'description' => "Legacy {$chargeType}",
        'effective_at' => now(),
        'status' => $status,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
        'created_by_user_id' => test()->user->id,
        'voided_at' => $status === FinanceCharge::STATUS_VOID ? now() : null,
        'voided_by_user_id' => $status === FinanceCharge::STATUS_VOID ? test()->user->id : null,
        'void_reason' => $status === FinanceCharge::STATUS_VOID ? 'legacy void' : null,
    ]);
}

it('backfills active and voided legacy retake or resit charges into idempotent debit obligations only', function (): void {
    $activeRetake = makeLegacyBackfillRetakeSource(1_500_000);
    $voidedResit = makeLegacyBackfillExamResitSource(750_000);

    $activeRetakeCharge = makeLegacyBackfillCharge(
        FinanceCharge::TYPE_RETAKE_FEE,
        1_500_000,
        FinanceCharge::STATUS_ACTIVE,
        CourseRetakeRegistration::class,
        $activeRetake->id,
    );
    $voidedResitCharge = makeLegacyBackfillCharge(
        FinanceCharge::TYPE_EXAM_RESIT_FEE,
        750_000,
        FinanceCharge::STATUS_VOID,
        ExamResitAttempt::class,
        $voidedResit->id,
    );

    $creditCharge = makeLegacyBackfillCharge(
        FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
        -250_000,
        FinanceCharge::STATUS_ACTIVE,
        CourseRetakeRegistration::class,
        $activeRetake->id,
    );
    $voucherCharge = makeLegacyBackfillCharge(
        FinanceCharge::TYPE_VOUCHER_CREDIT,
        -100_000,
        FinanceCharge::STATUS_ACTIVE,
        ExamResitAttempt::class,
        $voidedResit->id,
    );
    $deferCredit = makeLegacyBackfillCharge(
        FinanceCharge::TYPE_DEFER_CREDIT,
        -50_000,
        FinanceCharge::STATUS_ACTIVE,
        CourseRetakeRegistration::class,
        $activeRetake->id,
    );

    $this->artisan('finance:backfill-legacy-retake-resit-obligations')
        ->expectsOutputToContain('created 2')
        ->assertExitCode(0);

    $activeObligation = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION)
        ->where('source_ref', AcademicFinanceObligationSource::courseRetakeRegistrationRef($activeRetake))
        ->where('obligation_type', FinanceCharge::TYPE_RETAKE_FEE)
        ->firstOrFail();
    $voidedObligation = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($voidedResit))
        ->where('obligation_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->firstOrFail();

    expect(FinanceObligation::query()->count())->toBe(2)
        ->and(FinanceCharge::query()->count())->toBe(5)
        ->and($activeObligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $activeObligation->amount)->toBe(1_500_000.0)
        ->and($activeObligation->pricing_rule_version)->toBe('retake_fee:legacy_backfill')
        ->and($activeObligation->pricing_snapshot['provenance'])->toBe('legacy_backfill')
        ->and($activeObligation->pricing_snapshot['legacy_charge_id'])->toBe($activeRetakeCharge->id)
        ->and($voidedObligation->lifecycle_status)->toBe(FinanceObligation::STATUS_VOIDED)
        ->and((float) $voidedObligation->amount)->toBe(750_000.0)
        ->and($voidedObligation->pricing_rule_version)->toBe('exam_resit_fee:legacy_backfill')
        ->and($voidedObligation->pricing_snapshot['provenance'])->toBe('legacy_backfill')
        ->and($voidedObligation->pricing_snapshot['legacy_charge_id'])->toBe($voidedResitCharge->id)
        ->and($activeRetakeCharge->fresh()->finance_obligation_id)->toBe($activeObligation->id)
        ->and($voidedResitCharge->fresh()->finance_obligation_id)->toBe($voidedObligation->id)
        ->and($creditCharge->fresh()->finance_obligation_id)->toBeNull()
        ->and($voucherCharge->fresh()->finance_obligation_id)->toBeNull()
        ->and($deferCredit->fresh()->finance_obligation_id)->toBeNull();

    $this->artisan('finance:backfill-legacy-retake-resit-obligations')
        ->expectsOutputToContain('already linked 2')
        ->assertExitCode(0);

    expect(FinanceObligation::query()->count())->toBe(2)
        ->and(FinanceCharge::query()->count())->toBe(5)
        ->and($activeRetakeCharge->fresh()->finance_obligation_id)->toBe($activeObligation->id)
        ->and($voidedResitCharge->fresh()->finance_obligation_id)->toBe($voidedObligation->id);
});

it('reports source amount mismatches while preserving the existing charge amount', function (): void {
    $retake = makeLegacyBackfillRetakeSource(1_250_000);
    $charge = makeLegacyBackfillCharge(
        FinanceCharge::TYPE_RETAKE_FEE,
        1_500_000,
        FinanceCharge::STATUS_ACTIVE,
        CourseRetakeRegistration::class,
        $retake->id,
    );

    $this->artisan('finance:backfill-legacy-retake-resit-obligations', ['--report-mismatches' => true])
        ->expectsOutputToContain('mismatch(es) 1')
        ->expectsTable(
            ['Charge', 'Type', 'Reason', 'Source kind', 'Source ref', 'Charge amount', 'Source amount'],
            [
                [
                    $charge->id,
                    FinanceCharge::TYPE_RETAKE_FEE,
                    'amount_mismatch',
                    AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                    AcademicFinanceObligationSource::courseRetakeRegistrationRef($retake),
                    1_500_000.0,
                    1_250_000.0,
                ],
            ],
        )
        ->assertExitCode(0);

    $obligation = FinanceObligation::query()->firstOrFail();

    expect((float) $obligation->amount)->toBe(1_500_000.0)
        ->and($obligation->pricing_snapshot['provenance'])->toBe('legacy_backfill')
        ->and((float) $obligation->pricing_snapshot['legacy_charge_amount'])->toBe(1_500_000.0)
        ->and((float) $obligation->pricing_snapshot['legacy_source_amount'])->toBe(1_250_000.0)
        ->and($obligation->pricing_snapshot['source_amount_column'])->toBe('course_retake_registrations.retake_fee');
});

it('ignores source-less retake or resit charges that are already linked to obligations', function (): void {
    $obligation = FinanceObligation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => 'retake:already-intake-backed',
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 1_500_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'retake_fee:v1',
        'pricing_snapshot' => ['catalog_rule_version' => 'retake_fee:v1'],
        'accepted_at' => now(),
    ]);
    $charge = makeLegacyBackfillCharge(
        FinanceCharge::TYPE_RETAKE_FEE,
        1_500_000,
        FinanceCharge::STATUS_ACTIVE,
        null,
        null,
    );
    $charge->update(['finance_obligation_id' => $obligation->id]);

    $this->artisan('finance:backfill-legacy-retake-resit-obligations')
        ->expectsOutputToContain('No legacy retake/resit debit charges matched the backfill scope.')
        ->assertExitCode(0);

    expect(FinanceObligation::query()->count())->toBe(1)
        ->and(FinanceCharge::query()->count())->toBe(1)
        ->and($charge->fresh()->finance_obligation_id)->toBe($obligation->id);
});
