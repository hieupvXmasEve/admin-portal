<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Finance\Actions\ApproveRestorationProposalAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\CreateRestorationProposalAction;
use App\Modules\Finance\Actions\RejectRestorationProposalAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Queries\GetUnresolvedPriorAdjustmentQuery;
use App\Modules\Finance\Support\PendingScholarshipRestorationReader;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * Full fixture: campus, FALL/SPRING semesters, a student with a 30%
 * scholarship, maker, and a checker holding approve_scholarship_adjustment
 * at the campus. Mirrors ScholarshipSemesterAdjustmentApplyTest's fixture.
 *
 * @return array{campus: Campus, fall: Semester, spring: Semester, student: Student, definition: ScholarshipDefinition, award: StudentScholarshipAward, maker: User, checker: User}
 */
function restorationContext(): array
{
    $campus = Campus::factory()->create();
    $fall = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $spring = Semester::factory()->create(['start_date' => now()]);

    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => $fall->id,
        'intake_semester_id' => $fall->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'RESTORE30',
        'name' => '30 percent restorable',
        'description' => '30%',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    $award = StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $maker = User::factory()->create();
    $checker = User::factory()->create();

    $role = Role::firstOrCreate(['code' => 'restoration_checker_test'], ['name' => 'Restoration Checker Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'approve_scholarship_adjustment'],
        ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve adjustment', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );

    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('campus_user_roles')->insert([
        'user_id' => $checker->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The maker (proposer) holds restore_scholarship at the campus.
    $proposerRole = Role::firstOrCreate(['code' => 'restoration_proposer_test'], ['name' => 'Restoration Proposer Test']);
    $restorePermission = Permission::firstOrCreate(
        ['code' => 'restore_scholarship'],
        ['name' => 'restore_scholarship', 'display_name' => 'Restore scholarship', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $proposerRole->id, 'permission_id' => $restorePermission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('campus_user_roles')->insert([
        'user_id' => $maker->id, 'campus_id' => $campus->id, 'role_id' => $proposerRole->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $maker->id);

    return compact('campus', 'fall', 'spring', 'student', 'definition', 'award', 'maker', 'checker');
}

/**
 * Approve a FALL adjustment reducing the 30% award to $adjustedAmount%,
 * returning the created adjustment row. A FALL tuition charge is billed
 * FIRST (realistic ordering: the semester is already invoiced before the
 * dossier decision lands) so the apply transitions the row to `applied`,
 * not merely `pending_apply` — the restoration gate only carries forward
 * `applied` rows (per architecture).
 */
function restorationApplyFallAdjustment(array $ctx, float $adjustedAmount = 15.0, int $dossierId = 5001): ScholarshipSemesterAdjustment
{
    restorationTuitionCharge($ctx['student'], $ctx['fall']);

    // Source must differ from target — use a semester before FALL as source.
    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);

    $data = new ScholarshipAdjustmentData(
        student_id: $ctx['student']->id,
        source_semester_id: $source->id,
        target_semester_id: $ctx['fall']->id,
        adjusted_amount: $adjustedAmount,
        reason: 'Failed 2 courses',
        academic_dossier_id: $dossierId,
        maker_user_id: $ctx['maker']->id,
        checker_user_id: $ctx['checker']->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint(
            (string) $ctx['award']->scholarship_code,
            (string) $ctx['definition']->type,
            (string) $ctx['definition']->amount,
        ),
    );

    $result = app(ScholarshipAdjustmentContract::class)->apply($data);

    expect($result->accepted)->toBeTrue();

    return ScholarshipSemesterAdjustment::query()->findOrFail($result->adjustment_id);
}

function restorationTuitionCharge(Student $student, Semester $semester, float $amount = 20_000_000): FinanceCharge
{
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null,
        'source_system' => 'finance-test',
        'source_kind' => 'scholarship-restoration-test',
        'source_ref' => 'tuition:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'],
        'accepted_at' => now(),
    ]);

    return app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition',
    ]);
}

function restorationDiscount(int $awardId): ?InvoiceDiscount
{
    return InvoiceDiscount::query()
        ->where('discount_type', 'scholarship')
        ->where('reference_id', $awardId)
        ->latest('id')
        ->first();
}

// ---------------------------------------------------------------------
// GetUnresolvedPriorAdjustmentQuery
// ---------------------------------------------------------------------

it('returns the prior applied adjustment when no approved restoration exists', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx);

    $found = app(GetUnresolvedPriorAdjustmentQuery::class)
        ->handle($ctx['student']->id, $ctx['spring']->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($adjustment->id);
});

it('returns null once an approved restoration proposal exists', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    $found = app(GetUnresolvedPriorAdjustmentQuery::class)
        ->handle($ctx['student']->id, $ctx['spring']->id);

    expect($found)->toBeNull();
});

it('ignores adjustments whose target semester is not before the current semester', function () {
    $ctx = restorationContext();
    // Adjustment IS for the current (spring) semester itself — not "prior".
    $sourceForSpring = Semester::factory()->create(['start_date' => now()->subMonths(2)]);

    $data = new ScholarshipAdjustmentData(
        student_id: $ctx['student']->id,
        source_semester_id: $sourceForSpring->id,
        target_semester_id: $ctx['spring']->id,
        adjusted_amount: 15.0,
        reason: 'test',
        academic_dossier_id: 5002,
        maker_user_id: $ctx['maker']->id,
        checker_user_id: $ctx['checker']->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint(
            (string) $ctx['award']->scholarship_code,
            (string) $ctx['definition']->type,
            (string) $ctx['definition']->amount,
        ),
    );

    app(ScholarshipAdjustmentContract::class)->apply($data);

    $found = app(GetUnresolvedPriorAdjustmentQuery::class)
        ->handle($ctx['student']->id, $ctx['spring']->id);

    expect($found)->toBeNull();
});

// ---------------------------------------------------------------------
// Generation-time gate
// ---------------------------------------------------------------------

it('carries forward the reduced rate when generating tuition for the next semester with no approved restoration', function () {
    $ctx = restorationContext();
    restorationApplyFallAdjustment($ctx, 15.0);

    $charge = restorationTuitionCharge($ctx['student'], $ctx['spring']);
    $charge->refresh();

    // 15% of 20M — the carried-forward REDUCED rate, never the 30% award.
    expect((float) $charge->discount_amount)->toBe(3_000_000.0);

    $discount = restorationDiscount($ctx['award']->id);
    expect($discount)->not->toBeNull()
        ->and($discount->description)->toContain('carry-forward');
});

it('applies the ORIGINAL award once the restoration is approved', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);
    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    $charge = restorationTuitionCharge($ctx['student'], $ctx['spring']);
    $charge->refresh();

    // 30% of 20M — the full original award.
    expect((float) $charge->discount_amount)->toBe(6_000_000.0);
});

it('keeps the reduced rate when the restoration proposal is rejected', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);
    app(RejectRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    $charge = restorationTuitionCharge($ctx['student'], $ctx['spring']);
    $charge->refresh();

    expect((float) $charge->discount_amount)->toBe(3_000_000.0);
});

it('keeps the reduced rate when no restoration proposal exists at all', function () {
    $ctx = restorationContext();
    restorationApplyFallAdjustment($ctx, 15.0);

    $charge = restorationTuitionCharge($ctx['student'], $ctx['spring']);
    $charge->refresh();

    expect((float) $charge->discount_amount)->toBe(3_000_000.0);
});

it('lets a new adjustment for the next semester take precedence over the carried-forward one', function () {
    $ctx = restorationContext();
    restorationApplyFallAdjustment($ctx, 15.0);

    $springSource = Semester::factory()->create(['start_date' => now()->subMonths(2)]);
    $data = new ScholarshipAdjustmentData(
        student_id: $ctx['student']->id,
        source_semester_id: $springSource->id,
        target_semester_id: $ctx['spring']->id,
        adjusted_amount: 5.0,
        reason: 'New failure in spring source',
        academic_dossier_id: 5003,
        maker_user_id: $ctx['maker']->id,
        checker_user_id: $ctx['checker']->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint(
            (string) $ctx['award']->scholarship_code,
            (string) $ctx['definition']->type,
            (string) $ctx['definition']->amount,
        ),
    );
    $newAdjustmentResult = app(ScholarshipAdjustmentContract::class)->apply($data);
    expect($newAdjustmentResult->accepted)->toBeTrue();

    $charge = restorationTuitionCharge($ctx['student'], $ctx['spring']);
    $charge->refresh();

    // 5% of 20M, not the carried-forward 15%.
    expect((float) $charge->discount_amount)->toBe(1_000_000.0);
});

it('still resolves the adjusted amount for a late corrective charge on the OLD target semester after restoration is approved', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);
    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    // A corrective charge lands on FALL (the OLD target semester) AFTER
    // approval — the FALL adjustment itself is untouched (still `applied`),
    // so the active lookup for FALL still resolves the adjusted (15%) rate,
    // not the restored original 30%. Base is now the TOTAL FALL tuition
    // (20M original + 10M correction) per the shared resolver's multi-charge
    // rule (P2) — assert on the single upserted invoice discount row, the
    // same surface P2's own multi-charge test checks.
    restorationTuitionCharge($ctx['student'], $ctx['fall'], 10_000_000);

    expect((float) restorationDiscount($ctx['award']->id)->amount)->toBe(4_500_000.0);
});

// ---------------------------------------------------------------------
// CreateRestorationProposalAction / ApproveRestorationProposalAction
// ---------------------------------------------------------------------

it('refuses a second active restoration proposal for the same adjustment', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx);

    app(CreateRestorationProposalAction::class)->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    expect(fn () => app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result again', $ctx['maker']->id))
        ->toThrow(DomainException::class, 'duplicate_active_proposal');
});

it('refuses a proposal from a user without restore_scholarship at the campus', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx);
    $stranger = User::factory()->create();

    expect(fn () => app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $stranger->id))
        ->toThrow(DomainException::class, 'proposer_not_authorized');
});

it('lets the proposer approve their own restoration when they hold the approve permission', function () {
    // Product decision: separation of proposer and approver is not enforced —
    // the approve permission at the campus is the gate, and both user ids stay
    // on the proposal for audit.
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    // The maker only holds restore_scholarship by default; add the approve
    // permission so this asserts self-approval, not a permission failure.
    $role = Role::firstOrCreate(['code' => 'restoration_checker_test'], ['name' => 'Restoration Checker Test']);
    DB::table('campus_user_roles')->insert([
        'user_id' => $ctx['maker']->id, 'campus_id' => $ctx['campus']->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $ctx['maker']->id);

    $approved = app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['maker']->id);

    expect($approved->status)->toBe(ScholarshipRestorationProposal::STATUS_APPROVED)
        ->and((int) $approved->approved_by_user_id)->toBe($ctx['maker']->id)
        ->and((int) $approved->proposed_by_user_id)->toBe($ctx['maker']->id);
});

it('rejects approval by a checker without the campus permission', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    $stranger = User::factory()->create();

    expect(fn () => app(ApproveRestorationProposalAction::class)
        ->run($proposal->id, $stranger->id))
        ->toThrow(DomainException::class, 'checker_not_authorized');
});

it('closes the academic dossier via the Shared contract when a restoration is approved', function () {
    $ctx = restorationContext();

    $dossier = ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['campus']->id,
        'source_semester_id' => $ctx['fall']->id,
        'target_semester_id' => $ctx['fall']->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_APPLIED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $ctx['definition']->code,
        'original_type' => $ctx['definition']->type,
        'original_amount' => $ctx['definition']->amount,
        'created_by_user_id' => $ctx['maker']->id,
    ]);

    $adjustment = restorationApplyFallAdjustment($ctx, 15.0, $dossier->id);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    expect($proposal->fresh()->status)->toBe(ScholarshipRestorationProposal::STATUS_APPROVED)
        ->and($dossier->fresh()->status)->toBe(ScholarshipAdjustmentDossier::STATUS_CLOSED);
});

// ---------------------------------------------------------------------
// Partial restoration (Phase 1)
// ---------------------------------------------------------------------

it('carries forward the restored_amount of an approved PARTIAL restoration (percentage type)', function () {
    $ctx = restorationContext();
    // Award/definition is 30% (percentage) — adjusted down to 15%.
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Partial improvement', $ctx['maker']->id, 22.0);
    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    // Still carried (excluded only for approved FULL restore).
    $found = app(GetUnresolvedPriorAdjustmentQuery::class)
        ->handle($ctx['student']->id, $ctx['spring']->id);
    expect($found)->not->toBeNull();

    $charge = restorationTuitionCharge($ctx['student'], $ctx['spring']);
    $charge->refresh();

    // 22% of 20M — the restored_amount, not the original 15% or the full 30%.
    expect((float) $charge->discount_amount)->toBe(4_400_000.0);
});

it('never applies a partial restoration to the adjustment\'s OWN penalized target semester', function () {
    $ctx = restorationContext();
    // Fall (target semester) adjusted down to 15%.
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Partial improvement', $ctx['maker']->id, 22.0);
    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    // A late corrective charge lands on FALL — the OLD, already-penalized
    // target semester — AFTER the partial restore is approved. It must still
    // resolve at the adjustment's own 15%, never the restored 22%: a
    // restoration only ever changes what LATER semesters carry forward.
    restorationTuitionCharge($ctx['student'], $ctx['fall'], 10_000_000);

    // Base is now the TOTAL fall tuition (20M original + 10M correction);
    // 15% of 30M = 4,500,000 — NOT 15% of 20M (3M) and NOT 22% of anything.
    expect((float) restorationDiscount($ctx['award']->id)->amount)->toBe(4_500_000.0);
});

it('carries forward the restored_amount of an approved PARTIAL restoration (fixed_amount type)', function () {
    $campus = Campus::factory()->create();
    $fall = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $spring = Semester::factory()->create(['start_date' => now()]);
    $student = Student::factory()->create(['campus_id' => $campus->id, 'intake' => $fall->id, 'intake_semester_id' => $fall->id]);

    $definition = ScholarshipDefinition::create([
        'code' => 'RESTOREFIXED', 'name' => 'Fixed restorable', 'description' => 'fixed',
        'type' => 'fixed_amount', 'amount' => 10_000_000,
        'valid_from' => now()->subYear()->toDateString(), 'valid_until' => now()->addYear()->toDateString(), 'is_active' => true,
    ]);
    $award = StudentScholarshipAward::create(['student_id' => $student->id, 'scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString()]);

    $maker = User::factory()->create();
    $checker = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'restoration_checker_test'], ['name' => 'Restoration Checker Test']);
    $permission = Permission::firstOrCreate(['code' => 'approve_scholarship_adjustment'], ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve adjustment', 'module' => 'scholarship_adjustments', 'description' => 'test']);
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $checker->id, 'campus_id' => $campus->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
    $proposerRole = Role::firstOrCreate(['code' => 'restoration_proposer_test'], ['name' => 'Restoration Proposer Test']);
    $restorePermission = Permission::firstOrCreate(['code' => 'restore_scholarship'], ['name' => 'restore_scholarship', 'display_name' => 'Restore scholarship', 'module' => 'scholarship_adjustments', 'description' => 'test']);
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $proposerRole->id, 'permission_id' => $restorePermission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $maker->id, 'campus_id' => $campus->id, 'role_id' => $proposerRole->id, 'created_at' => now(), 'updated_at' => now()]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $maker->id);

    $ctx = compact('campus', 'fall', 'spring', 'student', 'definition', 'award', 'maker', 'checker');

    restorationTuitionCharge($student, $fall);
    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);
    $data = new ScholarshipAdjustmentData(
        student_id: $student->id, source_semester_id: $source->id, target_semester_id: $fall->id,
        adjusted_amount: 4_000_000, reason: 'Failed courses', academic_dossier_id: 6001,
        maker_user_id: $maker->id, checker_user_id: $checker->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint((string) $award->scholarship_code, (string) $definition->type, (string) $definition->amount),
    );
    $result = app(ScholarshipAdjustmentContract::class)->apply($data);
    $adjustment = ScholarshipSemesterAdjustment::query()->findOrFail($result->adjustment_id);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Partial improvement', $maker->id, 7_000_000);
    app(ApproveRestorationProposalAction::class)->run($proposal->id, $checker->id);

    $charge = restorationTuitionCharge($student, $spring);
    $charge->refresh();

    // Flat 7M — the restored_amount, not the original 4M nor the full 10M.
    expect((float) $charge->discount_amount)->toBe(7_000_000.0);
});

it('rejects a proposed restored_amount at or below the current floor', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    expect(fn () => app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Too low', $ctx['maker']->id, 15.0))
        ->toThrow(DomainException::class, 'restored_amount_out_of_bounds');
});

it('rejects a proposed restored_amount at or above the original amount', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    expect(fn () => app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Too high', $ctx['maker']->id, 30.0))
        ->toThrow(DomainException::class, 'restored_amount_out_of_bounds');
});

it('allows a repeat proposal after an approved PARTIAL restore, floored at the latest restored_amount', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    $first = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'First partial', $ctx['maker']->id, 20.0);
    app(ApproveRestorationProposalAction::class)->run($first->id, $ctx['checker']->id);

    // New floor is 20.0 (latest approved), not the original 15.0 adjusted_amount.
    expect(fn () => app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Too low now', $ctx['maker']->id, 18.0))
        ->toThrow(DomainException::class, 'restored_amount_out_of_bounds');

    $second = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Second partial', $ctx['maker']->id, 25.0);

    expect($second->status)->toBe(ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL);
});

it('blocks a repeat proposal after an approved FULL restore', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    $full = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Full restore', $ctx['maker']->id);
    app(ApproveRestorationProposalAction::class)->run($full->id, $ctx['checker']->id);

    expect(fn () => app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Another try', $ctx['maker']->id))
        ->toThrow(DomainException::class, 'duplicate_active_proposal');
});

// ---------------------------------------------------------------------
// PendingScholarshipRestorationReader (Phase 5 generation gate)
// ---------------------------------------------------------------------

it('flags a student as pending when their carried adjustment has a pending_approval restoration proposal', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);
    app(CreateRestorationProposalAction::class)->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    $map = app(PendingScholarshipRestorationReader::class)
        ->pendingByStudent([$ctx['student']->id], $ctx['spring']->id);

    expect($map[$ctx['student']->id])->toBeTrue();
});

it('does not flag a student with no restoration proposal at all', function () {
    $ctx = restorationContext();
    restorationApplyFallAdjustment($ctx, 15.0);

    $map = app(PendingScholarshipRestorationReader::class)
        ->pendingByStudent([$ctx['student']->id], $ctx['spring']->id);

    expect($map[$ctx['student']->id])->toBeFalse();
});

it('does not flag a student once the restoration proposal is approved (full or partial)', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);
    $proposal = app(CreateRestorationProposalAction::class)->run($adjustment->id, 'Partial', $ctx['maker']->id, 22.0);
    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    $map = app(PendingScholarshipRestorationReader::class)
        ->pendingByStudent([$ctx['student']->id], $ctx['spring']->id);

    // Approved-partial still carries (not excluded), but no LONGER pending —
    // generation should proceed at the effective (restored) rate, not block.
    expect($map[$ctx['student']->id])->toBeFalse();
});

it('does not flag a student once the restoration proposal is rejected', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);
    $proposal = app(CreateRestorationProposalAction::class)->run($adjustment->id, 'Clean result', $ctx['maker']->id);
    app(RejectRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    $map = app(PendingScholarshipRestorationReader::class)
        ->pendingByStudent([$ctx['student']->id], $ctx['spring']->id);

    expect($map[$ctx['student']->id])->toBeFalse();
});

// ---------------------------------------------------------------------
// pending_apply -> applied transition when the invoice arrives AFTER
// the decision (real production order: decided first, billed later)
// ---------------------------------------------------------------------

it('flips a decided adjustment from pending_apply to applied once its target-semester tuition charge lands later', function () {
    $ctx = restorationContext();

    // Decision happens BEFORE any FALL tuition charge exists — apply()'s own
    // timing guard finds no invoice yet and leaves the row pending_apply.
    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);
    $data = new ScholarshipAdjustmentData(
        student_id: $ctx['student']->id,
        source_semester_id: $source->id,
        target_semester_id: $ctx['fall']->id,
        adjusted_amount: 10.0,
        reason: 'Failed courses',
        academic_dossier_id: 7001,
        maker_user_id: $ctx['maker']->id,
        checker_user_id: $ctx['checker']->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint(
            (string) $ctx['award']->scholarship_code,
            (string) $ctx['definition']->type,
            (string) $ctx['definition']->amount,
        ),
    );
    $result = app(ScholarshipAdjustmentContract::class)->apply($data);
    $adjustment = ScholarshipSemesterAdjustment::query()->findOrFail($result->adjustment_id);

    expect($adjustment->status)->toBe(ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY);

    // The tuition charge for FALL (the adjustment's own target semester)
    // lands now, via the same CreateFinanceChargeAction path both batch-studio
    // commit (GenerateMajorChargesAction) and manual staff debit funnel
    // through.
    $charge = restorationTuitionCharge($ctx['student'], $ctx['fall']);
    $charge->refresh();

    expect($adjustment->fresh()->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED)
        ->and($adjustment->fresh()->applied_at)->not->toBeNull()
        // 10% of 20M — the decided rate, correctly applied on first billing.
        ->and((float) $charge->discount_amount)->toBe(2_000_000.0);
});

it('does NOT re-flip an already-applied adjustment when a later correction charge lands on the SAME target semester', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx, 15.0);

    expect($adjustment->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED);
    $appliedAt = $adjustment->applied_at;

    // A second, corrective charge on the SAME (already applied) target
    // semester must not re-run applyToLedger's guard/flip a second time.
    restorationTuitionCharge($ctx['student'], $ctx['fall'], 5_000_000);

    expect($adjustment->fresh()->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED)
        ->and($adjustment->fresh()->applied_at->eq($appliedAt))->toBeTrue();
});
