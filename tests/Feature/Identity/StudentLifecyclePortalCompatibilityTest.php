<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Http\Resources\Student\StudentResource;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\RecordStudentActionAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the student login shape while presenting Program Enrollment lifecycle status', function (): void {
    $user = User::factory()->create([
        'email' => 'lifecycle.student@example.test',
        'password' => bcrypt('password'),
        'status' => User::STATUS_ACTIVE,
    ]);
    $fromSemester = Semester::factory()->create();
    $returnSemester = Semester::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => 'intake_course',
        'academic_status' => 'active',
        'intake' => 1,
        'intake_semester_id' => $fromSemester->id,
    ]);

    RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER->value,
        'reason' => 'Approved study defer',
        'from_semester_id' => $fromSemester->id,
        'return_semester_id' => $returnSemester->id,
        'defer_scope_type' => 'FULL',
        'defer_fee_policy' => 'PRESERVE',
        'changed_by_user_id' => $user->id,
    ]);

    $this->postJson(route('api.student.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Lifecycle compatibility test',
    ])
        ->assertOk()
        ->assertJsonPath('data.student.id', $student->id)
        ->assertJsonPath('data.student.status', 'deferred')
        ->assertJsonStructure([
            'success',
            'timestamp',
            'data' => [
                'student' => ['id', 'student_id', 'user_id', 'full_name', 'email', 'status'],
                'token',
                'token_type',
                'expires_at',
            ],
        ]);

    expect($student->fresh()->status)->toBe('intake_course')
        ->and($user->fresh()->status)->toBe(User::STATUS_ACTIVE);
});

it('keeps Account Status independent while projecting dropout to the existing portal vocabulary', function (): void {
    $user = User::factory()->create([
        'email' => 'dropout.student@example.test',
        'password' => bcrypt('password'),
        'status' => User::STATUS_ACTIVE,
    ]);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => 'intake_course',
        'academic_status' => 'active',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DROPOUT->value,
        'reason' => 'Approved withdrawal',
        'dropout_semester_id' => $semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $this->postJson(route('api.student.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.student.status', 'dropout');

    expect($student->fresh()->status)->toBe('intake_course')
        ->and($user->fresh()->status)->toBe(User::STATUS_ACTIVE);
});

it('preserves the admission deferral discriminator in student-facing responses', function (): void {
    $user = User::factory()->create([
        'email' => 'admission.defer@example.test',
        'password' => bcrypt('password'),
        'status' => User::STATUS_ACTIVE,
    ]);
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => 'pending',
        'academic_status' => 'active',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    RecordStudentActionAction::run([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ADMISSION_DEFERRAL->value,
        'reason' => 'Admission deferred',
        'intended_intake_semester_id' => $semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $this->postJson(route('api.student.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('data.student.status', 'admission_deferred');
});

it('batch projects lifecycle status for paginated student resources', function (): void {
    $semester = Semester::factory()->create();
    Student::factory()->count(2)->create([
        'status' => 'intake_course',
        'academic_status' => 'active',
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    $payload = StudentResource::collection(Student::query()->paginate())->resolve();

    expect($payload)->toHaveCount(2)
        ->and(collect($payload)->pluck('status')->all())->toBe(['intake_course', 'intake_course']);
});
