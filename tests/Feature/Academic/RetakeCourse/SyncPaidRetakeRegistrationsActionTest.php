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
use App\Modules\Academic\Actions\SyncPaidRetakeRegistrationsAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createPaidPendingRetakeContext(bool $withExistingCourseRegistration = false): array
{
    $user = User::factory()->create();

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
        'current_enrollment' => $withExistingCourseRegistration ? 1 : 0,
    ]);
    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    $registration = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => CourseRetakeRegistration::STATUS_PAYMENT_PENDING,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ]);

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
            'is_retake_paid' => 'yes',
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
    $registration->update(['finance_charge_id' => $charge->id]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'external_ref' => 'DNG-PAID-RETAKE',
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
        SyncPaidRetakeRegistrationsAction::class,
    );

    return compact(
        'registration',
        'charge',
        'student',
        'courseOffering',
        'existingCourseRegistration',
    );
}

it('marks a fully paid retake registration paid and waits when no class registration exists', function () {
    ['registration' => $registration, 'student' => $student, 'courseOffering' => $courseOffering] = createPaidPendingRetakeContext();

    $result = app(SyncPaidRetakeRegistrationsAction::class)->runForStudent($student->id);

    $registration->refresh();
    $courseOffering->refresh();

    expect($result['checked'])->toBe(1)
        ->and($result['eligible'])->toBe(1)
        ->and($result['synced'])->toBe(0)
        ->and($result['waiting_for_class'])->toBe(1)
        ->and($registration->status)->toBe(CourseRetakeRegistration::STATUS_PAID)
        ->and($registration->paid_at)->not->toBeNull()
        ->and($registration->course_registration_id)->toBeNull()
        ->and($courseOffering->current_enrollment)->toBe(0);
});

it('reuses an existing target course registration instead of creating a duplicate', function () {
    [
        'registration' => $registration,
        'student' => $student,
        'courseOffering' => $courseOffering,
        'existingCourseRegistration' => $existingCourseRegistration,
    ] = createPaidPendingRetakeContext(withExistingCourseRegistration: true);

    $beforeCount = CourseRegistration::count();

    $result = app(SyncPaidRetakeRegistrationsAction::class)->runForStudent($student->id);

    $registration->refresh();
    $existingCourseRegistration->refresh();
    $courseOffering->refresh();

    expect($result['synced'])->toBe(1)
        ->and(CourseRegistration::count())->toBe($beforeCount)
        ->and($registration->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED)
        ->and($registration->course_registration_id)->toBe($existingCourseRegistration->id)
        ->and($existingCourseRegistration->is_retake)->toBeTrue()
        ->and($existingCourseRegistration->attempt_number)->toBe(2)
        ->and((float) $existingCourseRegistration->retake_fee)->toBe(5000000.0)
        ->and($courseOffering->current_enrollment)->toBe(1);
});

it('enrolls a paid registration when the previous auto-enroll stopped before linking the class', function () {
    [
        'registration' => $registration,
        'student' => $student,
        'existingCourseRegistration' => $existingCourseRegistration,
    ] = createPaidPendingRetakeContext(withExistingCourseRegistration: true);
    $registration->update([
        'status' => CourseRetakeRegistration::STATUS_PAID,
        'paid_at' => now()->subDay(),
    ]);

    $beforeCount = CourseRegistration::count();

    $result = app(SyncPaidRetakeRegistrationsAction::class)->runForStudent($student->id);

    $registration->refresh();
    $existingCourseRegistration->refresh();

    expect($result['checked'])->toBe(1)
        ->and($result['synced'])->toBe(1)
        ->and(CourseRegistration::count())->toBe($beforeCount)
        ->and($registration->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED)
        ->and($registration->course_registration_id)->toBe($existingCourseRegistration->id)
        ->and($existingCourseRegistration->is_retake)->toBeTrue()
        ->and($existingCourseRegistration->attempt_number)->toBe(2);
});

it('links a paid waiting registration when staff later adds the class registration', function () {
    [
        'registration' => $registration,
        'student' => $student,
        'courseOffering' => $courseOffering,
    ] = createPaidPendingRetakeContext();

    $result = app(SyncPaidRetakeRegistrationsAction::class)->runForStudent($student->id);
    $registration->refresh();

    expect($result['waiting_for_class'])->toBe(1)
        ->and($registration->status)->toBe(CourseRetakeRegistration::STATUS_PAID)
        ->and($registration->course_registration_id)->toBeNull();

    $staffCreatedRegistration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $courseOffering->semester_id,
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

    $registration->refresh();
    $staffCreatedRegistration->refresh();

    expect($registration->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED)
        ->and($registration->course_registration_id)->toBe($staffCreatedRegistration->id)
        ->and($staffCreatedRegistration->is_retake)->toBeTrue()
        ->and($staffCreatedRegistration->is_retake_paid)->toBeTrue()
        ->and($staffCreatedRegistration->attempt_number)->toBe(2);
});

it('does not auto-create a replacement when an enrolled class registration was removed', function () {
    [
        'registration' => $registration,
        'student' => $student,
        'courseOffering' => $courseOffering,
        'existingCourseRegistration' => $existingCourseRegistration,
    ] = createPaidPendingRetakeContext(withExistingCourseRegistration: true);

    app(SyncPaidRetakeRegistrationsAction::class)->runForStudent($student->id);
    $registration->refresh();
    $existingCourseRegistration->refresh();

    expect($registration->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED)
        ->and($registration->course_registration_id)->toBe($existingCourseRegistration->id);

    $existingCourseRegistration->update(['registration_status' => 'dropped']);
    $beforeCount = CourseRegistration::count();

    $result = app(SyncPaidRetakeRegistrationsAction::class)->runForStudent($student->id);
    $registration->refresh();

    expect($result['synced'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['details'][0]['status'])->toBe('needs_review')
        ->and(CourseRegistration::count())->toBe($beforeCount)
        ->and($registration->course_registration_id)->toBe($existingCourseRegistration->id);

    $existingCourseRegistration->update([
        'registration_status' => 'confirmed',
        'is_retake' => false,
        'is_retake_paid' => 'no',
    ]);

    $registration->refresh();
    $existingCourseRegistration->refresh();

    expect($registration->course_registration_id)->toBe($existingCourseRegistration->id)
        ->and($existingCourseRegistration->registration_status)->toBe('confirmed')
        ->and($existingCourseRegistration->is_retake)->toBeTrue()
        ->and($existingCourseRegistration->is_retake_paid)->toBeTrue();
});

it('supports dry-run without mutating the registration', function () {
    ['registration' => $registration, 'student' => $student] = createPaidPendingRetakeContext();

    $result = app(SyncPaidRetakeRegistrationsAction::class)->runForStudent($student->id, dryRun: true);

    $registration->refresh();

    expect($result['checked'])->toBe(1)
        ->and($result['eligible'])->toBe(1)
        ->and($result['synced'])->toBe(0)
        ->and($registration->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
        ->and($registration->course_registration_id)->toBeNull();
});
