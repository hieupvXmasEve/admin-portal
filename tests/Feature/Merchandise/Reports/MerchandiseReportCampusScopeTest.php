<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Merchandise\Models\Merchandise;
use App\Modules\Merchandise\Models\MerchandiseVariant;
use App\Modules\Merchandise\Models\RedemptionOrder;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const MERCHANDISE_REPORT_SCOPE_CSRF = 'merchandise-report-scope-test-csrf';

uses(RefreshDatabase::class);

function grantMerchandiseReportScopeRoleAtCampus(User $user, Campus $campus, string $roleCode): void
{
    $role = Role::where('code', $roleCode)->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);
}

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);

    $this->campusA = Campus::factory()->create();
    $this->campusB = Campus::factory()->create();

    $merchandise = Merchandise::factory()->create();
    $variantA = MerchandiseVariant::factory()->create(['merchandise_id' => $merchandise->id, 'campus_id' => $this->campusA->id]);
    $variantB = MerchandiseVariant::factory()->create(['merchandise_id' => $merchandise->id, 'campus_id' => $this->campusB->id]);

    $semester = Semester::factory()->create();
    $studentA = Student::factory()->create(['campus_id' => $this->campusA->id, 'intake' => $semester->id, 'intake_semester_id' => $semester->id]);
    $studentB = Student::factory()->create(['campus_id' => $this->campusB->id, 'intake' => $semester->id, 'intake_semester_id' => $semester->id]);

    $this->orderA = RedemptionOrder::factory()->create([
        'student_id' => $studentA->id,
        'campus_id' => $this->campusA->id,
        'status' => RedemptionOrder::STATUS_PENDING_REVIEW,
        'total_gold' => 40,
    ]);

    $this->orderB = RedemptionOrder::factory()->create([
        'student_id' => $studentB->id,
        'campus_id' => $this->campusB->id,
        'status' => RedemptionOrder::STATUS_PENDING_REVIEW,
        'total_gold' => 999,
    ]);

    $this->staff = User::factory()->create();
    grantMerchandiseReportScopeRoleAtCampus($this->staff, $this->campusA, 'super_admin');

    session(['_token' => MERCHANDISE_REPORT_SCOPE_CSRF, 'current_campus_id' => $this->campusA->id]);
});

it('scopes report data to only the campuses the caller is granted view_merchandise_report at', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data'));

    $response->assertOk();
    expect($response->json('data.gold_summary.gold_used'))->toBe(40)
        ->and($response->json('data.orders_by_status.counts.'.RedemptionOrder::STATUS_PENDING_REVIEW))->toBe(1)
        ->and(collect($response->json('data.granted_campuses'))->pluck('id')->all())->toBe([$this->campusA->id]);
});

it('forbids requesting a campus filter outside the caller\'s granted campuses', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data', ['campus_id' => $this->campusB->id]));

    $response->assertForbidden();
});

it('rejects a staff user without view_merchandise_report on the Inertia and JSON report routes', function () {
    $unauthorized = User::factory()->create();
    grantMerchandiseReportScopeRoleAtCampus($unauthorized, $this->campusA, 'can_bo');

    session(['_token' => MERCHANDISE_REPORT_SCOPE_CSRF, 'current_campus_id' => $this->campusA->id]);

    test()->actingAs($unauthorized)->get(route('merchandise.reports.index'))->assertForbidden();
    test()->actingAs($unauthorized)->getJson(route('merchandise.reports.data'))->assertForbidden();
});

it('rejects a staff user with zero merchandise grants entirely', function () {
    $stranger = User::factory()->create();

    test()->actingAs($stranger)->get(route('merchandise.reports.index'))->assertForbidden();
    test()->actingAs($stranger)->getJson(route('merchandise.reports.data'))->assertForbidden();
});
