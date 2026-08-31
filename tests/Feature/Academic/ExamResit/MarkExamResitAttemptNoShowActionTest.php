<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\CreateExamResitAttemptAction;
use App\Modules\Academic\Delivery\Actions\MarkExamResitAttemptNoShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $this->syllabus = SyllabusTemplate::factory()->create([
        'exam_resit_max_attempts' => 1,
    ]);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'campus_id' => $campus->id,
        'syllabus_template_id' => $this->syllabus->id,
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $this->record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'final_percentage' => 40,
    ]);

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => 'exam_resit_fee',
        'amount' => 500_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v1',
        'description' => 'Fixed exam resit fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->attempt = ExamResitAttempt::create([
        'student_id' => $student->id,
        'academic_record_id' => $this->record->id,
        'unit_id' => $offering->unit_id,
        'campus_id' => $campus->id,
        'original_semester_id' => $semester->id,
        'operation_semester_id' => $semester->id,
        'charge_semester_id' => $semester->id,
        'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'request_sequence' => 1,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
    ]);
});

it('marks a scheduled attempt as no-show and consumes one attempt', function () {
    $result = MarkExamResitAttemptNoShowAction::run([
        'attempt_id' => $this->attempt->id,
        'reason' => 'Sinh viên không đến dự thi',
    ]);

    $result->refresh();

    expect($result->status)->toBe(ExamResitAttempt::STATUS_NO_SHOW)
        ->and($result->no_show_at)->not->toBeNull()
        ->and($result->attempt_number)->toBe(1)
        ->and($result->result_snapshot['outcome'])->toBe('no_show')
        ->and($result->result_snapshot['fee_outcome'])->toBe('forfeit')
        ->and($result->result_snapshot['marked_by_user_id'])->toBe($this->user->id);

    // Owner rule #8: the academic record keeps its original failing grade.
    $record = $result->academicRecord;
    expect($record->is_passed)->toBeFalse()
        ->and((float) $record->final_percentage)->toBe(40.0);

    // Single consumed definition: exactly one attempt consumed.
    expect(ExamResitAttempt::consumedAttemptCount((int) $result->academic_record_id))->toBe(1);
});

it('does not consume another attempt when marking no-show twice', function () {
    MarkExamResitAttemptNoShowAction::run(['attempt_id' => $this->attempt->id]);

    $second = MarkExamResitAttemptNoShowAction::run(['attempt_id' => $this->attempt->id]);

    expect($second->attempt_number)->toBe(1)
        ->and(ExamResitAttempt::consumedAttemptCount((int) $this->attempt->academic_record_id))->toBe(1);
});

it('refuses to mark no-show for a non-scheduled attempt', function () {
    $this->attempt->update(['status' => ExamResitAttempt::STATUS_APPROVED]);

    MarkExamResitAttemptNoShowAction::run(['attempt_id' => $this->attempt->id]);
})->throws(RuntimeException::class);

it('blocks a second resit attempt once the no-show consumed the only attempt (live policy = 1)', function () {
    MarkExamResitAttemptNoShowAction::run(['attempt_id' => $this->attempt->id]);

    expect(fn () => app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->record->student_id,
        'academic_record_id' => $this->record->id,
        'operation_semester_id' => $this->record->semester_id,
        'charge_semester_id' => $this->record->semester_id,
        'campus_id' => $this->record->campus_id,
    ]))->toThrow(ValidationException::class);
});

it('allows a second resit attempt when the live syllabus max_attempts is above the consumed count', function () {
    MarkExamResitAttemptNoShowAction::run(['attempt_id' => $this->attempt->id]);

    // Live policy: raising the syllabus max re-opens the lane without code changes.
    $this->syllabus->update(['exam_resit_max_attempts' => 2]);

    $second = app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->record->student_id,
        'academic_record_id' => $this->record->id,
        'operation_semester_id' => $this->record->semester_id,
        'charge_semester_id' => $this->record->semester_id,
        'campus_id' => $this->record->campus_id,
    ]);

    expect($second)->toBeInstanceOf(ExamResitAttempt::class)
        ->and($second->status)->toBe(ExamResitAttempt::STATUS_APPROVED)
        ->and($second->attempt_number)->toBeNull();
});
