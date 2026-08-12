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
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const RESTORATION_HTTP_CSRF = 'restoration-http-test-csrf';

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function restorationHttpUser(Campus $campus, array $permissionCodes): User
{
    $user = User::factory()->create();
    $roleKey = implode('_', $permissionCodes) ?: 'none';
    $role = Role::firstOrCreate(
        ['code' => 'restoration_http_test_role_'.$roleKey],
        ['name' => 'Restoration HTTP Test Role '.$roleKey],
    );

    foreach ($permissionCodes as $code) {
        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'display_name' => $code, 'module' => 'scholarship_adjustments', 'description' => 'test'],
        );

        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    return $user;
}

/**
 * @return array{campus: Campus, fall: Semester, spring: Semester, student: Student, definition: ScholarshipDefinition, award: StudentScholarshipAward, adjustment: ScholarshipSemesterAdjustment}
 */
function restorationHttpScenario(): array
{
    $campus = Campus::factory()->create();
    $fall = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $spring = Semester::factory()->create(['start_date' => now()]);
    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);

    $student = Student::factory()->create([
        'campus_id' => $campus->id, 'intake' => $fall->id, 'intake_semester_id' => $fall->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'RESTOREHTTP', 'name' => 'HTTP restorable', 'description' => '30%',
        'type' => 'percentage', 'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(), 'valid_until' => now()->addYear()->toDateString(), 'is_active' => true,
    ]);
    $award = StudentScholarshipAward::create([
        'student_id' => $student->id, 'scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString(),
    ]);

    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null, 'source_system' => 'finance-test', 'source_kind' => 'restoration-http-test',
        'source_ref' => 'tuition:'.uniqid('', true), 'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED, 'amount' => 20_000_000, 'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test', 'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'], 'accepted_at' => now(),
    ]);
    app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id, 'student_id' => $student->id, 'semester_id' => $fall->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM, 'amount' => 20_000_000, 'description' => 'Tuition',
    ]);

    // ApplyScholarshipSemesterAdjustmentAction requires the checker to hold
    // approve_scholarship_adjustment AT the student's campus before it will
    // create the adjustment at all.
    $maker = User::factory()->create();
    $checker = restorationHttpUser($campus, ['approve_scholarship_adjustment']);

    $data = new ScholarshipAdjustmentData(
        student_id: $student->id, source_semester_id: $source->id, target_semester_id: $fall->id,
        adjusted_amount: 15.0, reason: 'Failed 2 courses', academic_dossier_id: 7001,
        maker_user_id: $maker->id, checker_user_id: $checker->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint((string) $award->scholarship_code, (string) $definition->type, (string) $definition->amount),
    );
    $result = app(ScholarshipAdjustmentContract::class)->apply($data);
    $adjustment = ScholarshipSemesterAdjustment::query()->findOrFail($result->adjustment_id);

    return compact('campus', 'fall', 'spring', 'student', 'definition', 'award', 'adjustment');
}

it('rejects propose from a user without restore_scholarship', function () {
    $ctx = restorationHttpScenario();
    $stranger = restorationHttpUser($ctx['campus'], []);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $ctx['campus']->id]);
    app()->instance('campus', $ctx['campus']);

    $response = $this->actingAs($stranger)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.propose', $ctx['adjustment']->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Clean result',
        ]);

    $response->assertForbidden();
});

it('rejects propose when the actor holds restore_scholarship at a DIFFERENT campus', function () {
    $ctx = restorationHttpScenario();
    $otherCampus = Campus::factory()->create();
    $stranger = restorationHttpUser($otherCampus, ['restore_scholarship']);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $otherCampus->id]);
    app()->instance('campus', $otherCampus);

    $response = $this->actingAs($stranger)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.propose', $ctx['adjustment']->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Clean result',
        ]);

    // Route gate passes (session campus holds the permission) but the
    // Action's own campus re-check (adjustment's campus) rejects it.
    $response->assertSessionHasErrors('error');
});

it('rejects a proposed restored_amount outside the bounds with 422', function () {
    $ctx = restorationHttpScenario();
    $maker = restorationHttpUser($ctx['campus'], ['restore_scholarship']);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $ctx['campus']->id]);
    app()->instance('campus', $ctx['campus']);

    $response = $this->actingAs($maker)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.propose', $ctx['adjustment']->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Too low',
            'restored_amount' => 15.0,
        ]);

    $response->assertSessionHasErrors('restored_amount');
});

it('proposes a PARTIAL restoration and approves it, closing the dossier', function () {
    $ctx = restorationHttpScenario();

    $dossier = ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $ctx['student']->id, 'campus_id' => $ctx['campus']->id,
        'source_semester_id' => $ctx['fall']->id, 'target_semester_id' => $ctx['fall']->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_APPLIED, 'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [], 'original_scholarship_code' => $ctx['definition']->code,
        'original_type' => $ctx['definition']->type, 'original_amount' => $ctx['definition']->amount,
        'created_by_user_id' => User::factory()->create()->id,
    ]);
    $ctx['adjustment']->update(['academic_dossier_id' => $dossier->id]);

    $maker = restorationHttpUser($ctx['campus'], ['restore_scholarship']);
    $checker = restorationHttpUser($ctx['campus'], ['approve_scholarship_adjustment']);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $ctx['campus']->id]);
    app()->instance('campus', $ctx['campus']);

    $proposeResponse = $this->actingAs($maker)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.propose', $ctx['adjustment']->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Partial improvement',
            'restored_amount' => 22.0,
        ]);
    $proposeResponse->assertSessionDoesntHaveErrors();

    $proposal = ScholarshipRestorationProposal::query()
        ->where('scholarship_semester_adjustment_id', $ctx['adjustment']->id)
        ->latest('id')->firstOrFail();

    expect((float) $proposal->restored_amount)->toBe(22.0);

    $approveResponse = $this->actingAs($checker)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.approve', $proposal->id), [
            '_token' => RESTORATION_HTTP_CSRF,
        ]);
    $approveResponse->assertSessionDoesntHaveErrors();

    expect($proposal->fresh()->status)->toBe(ScholarshipRestorationProposal::STATUS_APPROVED)
        ->and($dossier->fresh()->status)->toBe(ScholarshipAdjustmentDossier::STATUS_CLOSED);
});

it('rejects a proposal via the reject endpoint', function () {
    $ctx = restorationHttpScenario();
    $maker = restorationHttpUser($ctx['campus'], ['restore_scholarship']);
    $checker = restorationHttpUser($ctx['campus'], ['approve_scholarship_adjustment']);

    $proposal = app(CreateRestorationProposalAction::class)
        ->run($ctx['adjustment']->id, 'Clean result', $maker->id);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $ctx['campus']->id]);
    app()->instance('campus', $ctx['campus']);

    $response = $this->actingAs($checker)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.reject', $proposal->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Still failing',
        ]);
    $response->assertSessionDoesntHaveErrors();

    expect($proposal->fresh()->status)->toBe(ScholarshipRestorationProposal::STATUS_REJECTED);
});

it('blocks propose while a pending proposal already exists', function () {
    $ctx = restorationHttpScenario();
    $maker = restorationHttpUser($ctx['campus'], ['restore_scholarship']);

    app(CreateRestorationProposalAction::class)->run($ctx['adjustment']->id, 'First', $maker->id);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $ctx['campus']->id]);
    app()->instance('campus', $ctx['campus']);

    $response = $this->actingAs($maker)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.propose', $ctx['adjustment']->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Second attempt',
        ]);

    $response->assertSessionHasErrors('error');
});

it('allows propose again after an approved PARTIAL restore, floored at the latest restored_amount', function () {
    $ctx = restorationHttpScenario();
    $maker = restorationHttpUser($ctx['campus'], ['restore_scholarship']);
    $checker = restorationHttpUser($ctx['campus'], ['approve_scholarship_adjustment']);

    $first = app(CreateRestorationProposalAction::class)
        ->run($ctx['adjustment']->id, 'First partial', $maker->id, 20.0);
    app(ApproveRestorationProposalAction::class)->run($first->id, $checker->id);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $ctx['campus']->id]);
    app()->instance('campus', $ctx['campus']);

    $response = $this->actingAs($maker)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.propose', $ctx['adjustment']->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Second partial',
            'restored_amount' => 25.0,
        ]);
    $response->assertSessionDoesntHaveErrors();

    $second = ScholarshipRestorationProposal::query()
        ->where('scholarship_semester_adjustment_id', $ctx['adjustment']->id)
        ->latest('id')->firstOrFail();

    expect($second->status)->toBe(ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL)
        ->and((float) $second->restored_amount)->toBe(25.0);
});

it('blocks propose again after an approved FULL restore', function () {
    $ctx = restorationHttpScenario();
    $maker = restorationHttpUser($ctx['campus'], ['restore_scholarship']);
    $checker = restorationHttpUser($ctx['campus'], ['approve_scholarship_adjustment']);

    $first = app(CreateRestorationProposalAction::class)->run($ctx['adjustment']->id, 'Full restore', $maker->id);
    app(ApproveRestorationProposalAction::class)->run($first->id, $checker->id);

    session(['_token' => RESTORATION_HTTP_CSRF, 'current_campus_id' => $ctx['campus']->id]);
    app()->instance('campus', $ctx['campus']);

    $response = $this->actingAs($maker)
        ->withHeader('X-CSRF-TOKEN', RESTORATION_HTTP_CSRF)
        ->post(route('finance.scholarship-restorations.propose', $ctx['adjustment']->id), [
            '_token' => RESTORATION_HTTP_CSRF,
            'reason' => 'Another try',
        ]);

    $response->assertSessionHasErrors('error');
});
