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

it('rejects approval when maker and checker are the same user', function () {
    $ctx = restorationContext();
    $adjustment = restorationApplyFallAdjustment($ctx);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    expect(fn () => app(ApproveRestorationProposalAction::class)
        ->run($proposal->id, $ctx['maker']->id))
        ->toThrow(DomainException::class, 'maker_is_checker');
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
