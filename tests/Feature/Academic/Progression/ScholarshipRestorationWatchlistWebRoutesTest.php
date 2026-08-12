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
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Auth\Access\Response as AccessResponse;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
        Authorize::class,
    ]);

    Gate::before(fn (): AccessResponse => AccessResponse::allow());

    $campus = Campus::factory()->create();
    session(['current_campus_id' => $campus->id]);
    actingAs(User::factory()->create());

    Semester::factory()->create(['code' => '2026SP', 'start_date' => now()]);
});

it('renders the scholarship restoration watchlist report', function (): void {
    get(route('reports.scholarship-restorations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Reports/ScholarshipRestorationWatchlist/Index')
            ->where('filters.per_page', 25)
            ->has('options.semesters')
            ->has('options.verdicts')
            ->has('options.proposal_states')
        );
});

it('renders an empty watchlist for a campus with nothing carried', function (): void {
    get(route('reports.scholarship-restorations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.total', 0)
        );
});

it('streams the watchlist export as a spreadsheet', function (): void {
    get(route('reports.scholarship-restorations.export'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('accepts the documented filters without a validation error', function (): void {
    get(route('reports.scholarship-restorations.index', [
        'search' => 'test',
        'verdict' => 'clean',
        'proposal_state' => 'pending',
        'per_page' => 10,
    ]))->assertOk();
});

it('rejects an invalid verdict filter', function (): void {
    get(route('reports.scholarship-restorations.index', ['verdict' => 'bogus']))
        ->assertSessionHasErrors('verdict');
});

it('renders a real carried row end-to-end and streams an export that maps it without error', function (): void {
    $campus = session('current_campus_id');
    $fall = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);

    $student = Student::factory()->create(['campus_id' => $campus, 'intake' => $fall->id, 'intake_semester_id' => $fall->id]);

    $definition = ScholarshipDefinition::create([
        'code' => 'WATCHLISTROUTE', 'name' => 'Route test', 'description' => '30%',
        'type' => 'percentage', 'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(), 'valid_until' => now()->addYear()->toDateString(), 'is_active' => true,
    ]);
    StudentScholarshipAward::create(['student_id' => $student->id, 'scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString()]);

    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null, 'source_system' => 'finance-test', 'source_kind' => 'watchlist-route-test',
        'source_ref' => 'tuition:'.uniqid('', true), 'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED, 'amount' => 20_000_000, 'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test', 'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'], 'accepted_at' => now(),
    ]);
    app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id, 'student_id' => $student->id, 'semester_id' => $fall->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM, 'amount' => 20_000_000, 'description' => 'Tuition',
    ]);

    $maker = User::factory()->create();
    $checker = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'watchlist_route_checker_test'], ['name' => 'Watchlist Route Checker Test']);
    $permission = Permission::firstOrCreate(['code' => 'approve_scholarship_adjustment'], ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve', 'module' => 'scholarship_adjustments', 'description' => 'test']);
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $checker->id, 'campus_id' => $campus, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);

    $data = new ScholarshipAdjustmentData(
        student_id: $student->id, source_semester_id: $source->id, target_semester_id: $fall->id,
        adjusted_amount: 15.0, reason: 'Failed courses', academic_dossier_id: 8001,
        maker_user_id: $maker->id, checker_user_id: $checker->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint($definition->code, $definition->type, (string) $definition->amount),
    );
    app(ScholarshipAdjustmentContract::class)->apply($data);

    get(route('reports.scholarship-restorations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.total', 1)
            ->where('rows.data.0.student.student_code', $student->student_id)
            ->where('rows.data.0.proposal_state', 'none')
        );

    get(route('reports.scholarship-restorations.export'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
