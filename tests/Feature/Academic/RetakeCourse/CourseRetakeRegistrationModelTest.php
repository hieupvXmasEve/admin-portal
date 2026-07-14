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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRetakeRegistration(string $status = 'approved', array $overrides = []): CourseRetakeRegistration
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

    return CourseRetakeRegistration::create(array_merge([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'status' => $status,
        'attempt_number' => 2,
        'retake_fee' => 5000000,
        'approved_by_user_id' => $user->id,
        'approved_at' => now(),
    ], $overrides));
}

// =====================
// State Transition Tests
// =====================

it('transitions from approved to payment_pending', function () {
    $reg = createRetakeRegistration('approved');
    $user = User::factory()->create();

    $reg->transitionToPaymentPending($user->id);

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($reg->charge_created_by_user_id)->toBe($user->id);
    expect($reg->charge_created_at)->not->toBeNull();
});

it('throws when transitioning to payment_pending from non-approved status', function () {
    $reg = createRetakeRegistration('payment_pending');
    $user = User::factory()->create();

    $reg->transitionToPaymentPending($user->id);
})->throws(RuntimeException::class, 'Cannot transition to payment_pending from status: payment_pending');

it('transitions from payment_pending to paid', function () {
    $reg = createRetakeRegistration('payment_pending');

    $reg->transitionToPaid();

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_PAID);
    expect($reg->paid_at)->not->toBeNull();
});

it('throws when transitioning to paid from non-payment_pending status', function () {
    $reg = createRetakeRegistration('approved');

    $reg->transitionToPaid();
})->throws(RuntimeException::class, 'Cannot transition to paid from status: approved');

it('transitions from paid to enrolled', function () {
    $reg = createRetakeRegistration('paid');

    $courseRegistration = CourseRegistration::create([
        'student_id' => $reg->student_id,
        'course_offering_id' => $reg->course_offering_id,
        'semester_id' => $reg->semester_id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'is_retake' => true,
        'attempt_number' => $reg->attempt_number,
        'credit_points' => 10,
        'credit_hours' => 3,
    ]);

    $reg->transitionToEnrolled($courseRegistration->id);

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_ENROLLED);
    expect($reg->course_registration_id)->toBe($courseRegistration->id);
    expect($reg->enrolled_at)->not->toBeNull();
});

it('throws when transitioning to enrolled from non-paid status', function () {
    $reg = createRetakeRegistration('approved');

    $reg->transitionToEnrolled(99999);
})->throws(RuntimeException::class, 'Cannot transition to enrolled from status: approved');

it('cancels from approved status', function () {
    $reg = createRetakeRegistration('approved');
    $user = User::factory()->create();

    $reg->cancel($user->id, 'Student request');

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
    expect($reg->cancelled_by_user_id)->toBe($user->id);
    expect($reg->cancelled_at)->not->toBeNull();
    expect($reg->cancellation_reason)->toBe('Student request');
});

it('cancels from payment_pending status', function () {
    $reg = createRetakeRegistration('payment_pending');
    $user = User::factory()->create();

    $reg->cancel($user->id, 'Fee waived');

    $reg->refresh();
    expect($reg->status)->toBe(CourseRetakeRegistration::STATUS_CANCELLED);
});

it('throws when cancelling from non-cancellable status', function () {
    $reg = createRetakeRegistration('paid');

    $reg->cancel(1, 'Should fail');
})->throws(RuntimeException::class, 'Cannot cancel from status: paid');

// =====================
// Scope Tests
// =====================

it('scopes nonTerminal correctly', function () {
    createRetakeRegistration('approved');
    createRetakeRegistration('payment_pending');
    createRetakeRegistration('paid');
    createRetakeRegistration('enrolled');
    createRetakeRegistration('cancelled');

    $nonTerminal = CourseRetakeRegistration::nonTerminal()->get();
    expect($nonTerminal)->toHaveCount(3);
    expect($nonTerminal->pluck('status')->sort()->values()->all())
        ->toBe(['approved', 'paid', 'payment_pending']);
});

// =====================
// Helper Tests
// =====================

it('identifies terminal status correctly', function () {
    $enrolled = createRetakeRegistration('enrolled');
    $cancelled = createRetakeRegistration('cancelled');
    $approved = createRetakeRegistration('approved');

    expect($enrolled->isTerminal())->toBeTrue();
    expect($cancelled->isTerminal())->toBeTrue();
    expect($approved->isTerminal())->toBeFalse();
});

it('identifies cancellable status correctly', function () {
    $approved = createRetakeRegistration('approved');
    $pending = createRetakeRegistration('payment_pending');
    $paid = createRetakeRegistration('paid');

    expect($approved->isCancellable())->toBeTrue();
    expect($pending->isCancellable())->toBeTrue();
    expect($paid->isCancellable())->toBeFalse();
});
