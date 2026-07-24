<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function studentPortalFinanceSummaryStudent(): Student
{
    $semester = Semester::factory()->active()->create();

    return Student::factory()->forCampus(Campus::factory()->create())->create([
        'user_id' => User::factory()->create()->id,
        'intake' => 2024,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
        'status' => 'active',
    ]);
}

function studentPortalFinanceSummaryInvoice(Student $student, Semester $semester): StudentInvoice
{
    return StudentInvoice::query()->create([
        'invoice_number' => 'INV-PORTAL-FINANCE-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);
}

it('preserves the legacy finance summary and semester response shapes', function (): void {
    $student = studentPortalFinanceSummaryStudent();
    $semester = Semester::factory()->active()->create();
    studentPortalFinanceSummaryInvoice($student, $semester);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.finance.index'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.semesters.0.semester_id', $semester->id)
        ->assertJsonPath('data.semesters.0.semester_code', $semester->code)
        ->assertJsonPath('data.semesters.0.status', 'no_fee');

    $this->getJson(route('v1.student.finance.semester', $semester))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.semester.id', $semester->id)
        ->assertJsonPath('data.semester.code', $semester->code)
        ->assertJsonPath('data.charges', [])
        ->assertJsonPath('data.payments', []);
});

it('keeps the finance summary available through an active parent proxy grant', function (): void {
    $student = studentPortalFinanceSummaryStudent();
    $otherStudent = studentPortalFinanceSummaryStudent();
    $studentSemester = Semester::factory()->active()->create();
    $otherStudentSemester = Semester::factory()->active()->create();
    studentPortalFinanceSummaryInvoice($student, $studentSemester);
    studentPortalFinanceSummaryInvoice($otherStudent, $otherStudentSemester);
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Finance Summary Guardian',
        'relationship_type' => 'parent',
        'email' => 'finance-summary-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.finance.index'), ['X-Student-ID' => $student->student_id])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.semesters')
        ->assertJsonPath('data.semesters.0.semester_id', $studentSemester->id)
        ->assertJsonPath('data.semesters.0.semester_code', $studentSemester->code);
});

it('rejects a parent proxy finance summary read for an ungranted student', function (): void {
    $grantedStudent = studentPortalFinanceSummaryStudent();
    $ungrantedStudent = studentPortalFinanceSummaryStudent();
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $grantedStudent->id, [[
        'full_name' => 'Granted Finance Summary Guardian',
        'relationship_type' => 'parent',
        'email' => 'granted-finance-summary-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(route('v1.student.finance.index'), ['X-Student-ID' => $ungrantedStudent->student_id])
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'AUTHORIZATION_ERROR');
});

it('preserves the missing-semester finance response', function (): void {
    $student = studentPortalFinanceSummaryStudent();

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.finance.semester', 999999))
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Semester not found')
        ->assertJsonPath('errors.0.code', 'NOT_FOUND');
});
