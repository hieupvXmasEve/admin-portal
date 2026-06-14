<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * UI-SAFE-3: the server `store` applies a block-count override for any student in
 * the payload — regardless of which preview page they were on — and defaults
 * students NOT in the payload to their max_chargeable_blocks. This is the
 * contract the Vue now fulfils by sending every override the user set across all
 * pages (not just the current page).
 */
function makeEgcStoreUser(Campus $campus): User
{
    $user = User::factory()->create();

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')
        ->andReturn(['view_egc_finance_operations', 'generate_egc_finance_charges']);
    app()->instance(PermissionService::class, $mock);

    session(['current_campus_id' => $campus->id]);

    return $user;
}

function makeEgcEligibleStudent(Campus $campus, Semester $semester, string $code): Student
{
    return Student::factory()->create([
        'campus_id' => $campus->id,
        'student_id' => $code,
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 1,
        'gc_total_levels' => 6,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

it('applies a per-student block override and defaults un-sent students to max (UI-SAFE-3)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $user = makeEgcStoreUser($campus);

    // Both students are eligible for the max of 2 blocks.
    $studentA = makeEgcEligibleStudent($campus, $semester, 'EGCA0001');
    $studentB = makeEgcEligibleStudent($campus, $semester, 'EGCB0002');

    // Payload overrides ONLY student A to 1 block (as if adjusted on some page);
    // student B is omitted (untouched) and must fall back to max (2).
    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->post(route('finance.egc.charges.store'), [
            '_token' => 'test-csrf-token',
            'semester_id' => $semester->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'students' => [
                ['student_id' => $studentA->id, 'block_count' => 1, 'current_level' => 1],
            ],
        ])
        ->assertRedirect();

    $countFor = fn (Student $s) => FinanceCharge::query()
        ->where('student_id', $s->id)
        ->where('semester_id', $semester->id)
        ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->count();

    // A honoured the override (1 block); B defaulted to max (2 blocks).
    expect($countFor($studentA))->toBe(1)
        ->and($countFor($studentB))->toBe(2);
});

it('clamps a stale override above the current max_chargeable_blocks (UI-SAFE-3)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $user = makeEgcStoreUser($campus);

    // Student at level 5 of 6 → only ONE chargeable block remains (max = 1).
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'student_id' => 'EGCMAX01',
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 5,
        'gc_total_levels' => 6,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    // A stale override of 2 (carried over from another scope) must be clamped to 1.
    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->post(route('finance.egc.charges.store'), [
            '_token' => 'test-csrf-token',
            'semester_id' => $semester->id,
            'due_date' => now()->addDays(30)->toDateString(),
            'students' => [
                ['student_id' => $student->id, 'block_count' => 2, 'current_level' => 5],
            ],
        ])
        ->assertRedirect();

    $charges = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->count();

    expect($charges)->toBe(1);
});
