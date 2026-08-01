<?php

declare(strict_types=1);

use App\Models\GoldTransaction;
use App\Models\ParentProfile;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\GoldService;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $attributes */
function goldOwnershipStudent(array $attributes = []): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->create(array_replace([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
        'status' => 'active',
    ], $attributes));
}

it('lets a student read their own transaction', function () {
    $student = goldOwnershipStudent();
    $tx = app(GoldService::class)->addGold($student, 50, GoldTransaction::SOURCE_EVENT, 1, 'test');

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.wallet.transactions.show', $tx))
        ->assertOk()
        ->assertJsonPath('data.id', $tx->id);
});

it("returns 403 when a student requests another student's transaction by id", function () {
    $owner = goldOwnershipStudent();
    $other = goldOwnershipStudent();
    $tx = app(GoldService::class)->addGold($owner, 50, GoldTransaction::SOURCE_EVENT, 1, 'test');

    Sanctum::actingAs($other);

    $this->getJson(route('v1.student.wallet.transactions.show', $tx))->assertForbidden();
});

it(
    "returns 403 — not the owner's transaction — when a parent proxying for student A requests student B's transaction id (closes the IDOR: Auth::guard('student') was null for a parent actor and skipped the ownership check entirely)",
    function () {
        $studentA = goldOwnershipStudent();
        $studentB = goldOwnershipStudent();
        $transactionForB = app(GoldService::class)->addGold($studentB, 75, GoldTransaction::SOURCE_EVENT, 1, 'test');

        $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $studentA->id, [[
            'full_name' => 'Gold Ownership Guardian',
            'relationship_type' => 'parent',
            'email' => 'gold-ownership-guardian@example.test',
            'is_primary' => true,
        ]])[0];
        $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
        $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

        Sanctum::actingAs($parent);

        $this->getJson(
            route('v1.student.wallet.transactions.show', $transactionForB),
            ['X-Student-ID' => $studentA->student_id]
        )->assertForbidden();
    }
);

it('lets a parent proxying for the correct student read that student\'s own transaction', function () {
    $student = goldOwnershipStudent();
    $tx = app(GoldService::class)->addGold($student, 40, GoldTransaction::SOURCE_EVENT, 1, 'test');

    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Gold Ownership Guardian 2',
        'relationship_type' => 'parent',
        'email' => 'gold-ownership-guardian-2@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    Sanctum::actingAs($parent);

    $this->getJson(
        route('v1.student.wallet.transactions.show', $tx),
        ['X-Student-ID' => $student->student_id]
    )->assertOk()->assertJsonPath('data.id', $tx->id);
});
