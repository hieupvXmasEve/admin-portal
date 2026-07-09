<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Actions\AutoEnrollRetakeCourseAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAutoEnrollRegistration(array $overrides = [], bool $withExistingCourseRegistration = false, bool $markPaid = true): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'max_capacity' => 50,
        'current_enrollment' => 10,
    ]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);
    $user = User::factory()->create();

    $registration = CourseRetakeRegistration::create(array_merge([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ], $overrides));

    $existingCourseRegistration = null;
    if ($withExistingCourseRegistration) {
        $existingCourseRegistration = CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'is_retake' => false,
            'attempt_number' => 1,
            'retake_fee' => 0,
            'is_retake_paid' => 'no',
            'credit_points' => $courseOffering->unit->credit_points ?? 0,
            'credit_hours' => $courseOffering->unit->credit_hours ?? 0,
        ]);
    }

    $obligation = FinanceObligation::query()->create([
        'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
        'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
        'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
        'obligation_type' => AcademicFinanceObligationSource::RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 5000000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => ['test' => true],
        'accepted_at' => now(),
    ]);

    $charge = app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5000000,
        'description' => 'Retake fee',
        'created_by_user_id' => $user->id,
    ]);

    if ($markPaid) {
        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => 5000000,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'dng',
            'external_ref' => 'DNG-AUTO-RETAKE',
            'paid_at' => now(),
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $line = InvoiceLine::where('charge_id', $charge->id)->firstOrFail();
        app(SettlementService::class)->createPaymentApplication(
            $payment,
            $line,
            5000000,
            'application',
            $user->id,
            AutoEnrollRetakeCourseAction::class,
        );
    }

    return compact('registration', 'charge', 'student', 'courseOffering', 'existingCourseRegistration', 'obligation');
}

it('marks the retake registration paid and waits when no class registration exists', function () {
    ['registration' => $reg, 'courseOffering' => $offering] = createAutoEnrollRegistration();

    $beforeCount = CourseRegistration::count();

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed((int) $reg->id);

    $reg->refresh();
    $offering->refresh();

    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_PAID);
    expect($reg->paid_at)->not->toBeNull();
    expect($reg->enrolled_at)->toBeNull();
    expect($reg->course_registration_id)->toBeNull();
    expect(CourseRegistration::count())->toBe($beforeCount);
    expect($offering->current_enrollment)->toBe(10);
});

it('links an existing staff-created course registration when payment is confirmed', function () {
    [
        'registration' => $reg,
        'courseOffering' => $offering,
        'existingCourseRegistration' => $existingCourseRegistration,
    ] = createAutoEnrollRegistration(withExistingCourseRegistration: true);

    $beforeCount = CourseRegistration::count();

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed((int) $reg->id);

    $reg->refresh();
    $existingCourseRegistration->refresh();
    $offering->refresh();

    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED);
    expect($reg->paid_at)->not->toBeNull();
    expect($reg->enrolled_at)->not->toBeNull();
    expect($reg->course_registration_id)->toBe($existingCourseRegistration->id);
    expect(CourseRegistration::count())->toBe($beforeCount);
    expect($existingCourseRegistration->is_retake)->toBeTrue();
    expect($existingCourseRegistration->registration_method)->toBe('admin_override');
    expect($existingCourseRegistration->registration_status)->toBe('confirmed');
    expect($offering->current_enrollment)->toBe(10);
});

it('skips when finance obligation is not settled', function () {
    ['registration' => $reg] = createAutoEnrollRegistration(markPaid: false);

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed((int) $reg->id);

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('skips registration not at payment_pending status', function () {
    ['registration' => $reg] = createAutoEnrollRegistration();

    // Manually set to approved (edge case: shouldn't happen but guard against it)
    $reg->update(['status' => CourseRetakeRegistration::STATUS_APPROVED]);

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed((int) $reg->id);

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_APPROVED);
});

it('does not create a class registration even when the course offering is full', function () {
    ['registration' => $reg, 'courseOffering' => $offering] = createAutoEnrollRegistration();

    $offering->update([
        'max_capacity' => 10,
        'current_enrollment' => 10,
    ]);

    AutoEnrollRetakeCourseAction::handlePaymentConfirmed((int) $reg->id);

    $reg->refresh();
    $offering->refresh();

    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_PAID);
    expect($reg->course_registration_id)->toBeNull();
    expect($offering->current_enrollment)->toBe(10);
});
