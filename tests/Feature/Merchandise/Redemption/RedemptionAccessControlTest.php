<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\GoldTransaction;
use App\Models\Merchandise;
use App\Models\MerchandiseVariant;
use App\Models\ParentProfile;
use App\Models\RedemptionOrder;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Merchandise\Support\RedemptionService;
use App\Services\GoldService;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

const REDEMPTION_ACCESS_CSRF = 'redemption-access-test-csrf';

uses(RefreshDatabase::class);

function grantRedemptionRoleAtCampus(User $user, Campus $campus, string $roleCode): void
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

it('forbids a staff member granted only at campus A from approving a campus B order', function () {
    Cache::flush();
    test()->seed(RoleAndPermissionSeeder::class);

    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();
    $semester = Semester::factory()->create();

    $student = Student::factory()->create([
        'campus_id' => $campusB->id,
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    $merchandise = Merchandise::factory()->create(['gold_price' => 20]);
    $variant = MerchandiseVariant::factory()->create([
        'merchandise_id' => $merchandise->id,
        'campus_id' => $campusB->id,
        'stock_quantity' => 10,
    ]);

    app(GoldService::class)->addGold($student, 100, GoldTransaction::SOURCE_EVENT, 1, 'funding');
    $order = app(RedemptionService::class)->createOrder(
        $student,
        [['variant_id' => $variant->id, 'quantity' => 1]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    );
    expect($order->campus_id)->toBe($campusB->id);

    $staff = User::factory()->create();
    grantRedemptionRoleAtCampus($staff, $campusA, 'super_admin');

    session(['_token' => REDEMPTION_ACCESS_CSRF, 'current_campus_id' => $campusA->id]);

    test()->actingAs($staff)
        ->post(route('redemption-orders.approve', $order), ['_token' => REDEMPTION_ACCESS_CSRF])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(RedemptionOrder::STATUS_PENDING_REVIEW);
});

it('allows a staff member granted at the order campus to approve it', function () {
    Cache::flush();
    test()->seed(RoleAndPermissionSeeder::class);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();

    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    $merchandise = Merchandise::factory()->create(['gold_price' => 20]);
    $variant = MerchandiseVariant::factory()->create([
        'merchandise_id' => $merchandise->id,
        'campus_id' => $campus->id,
        'stock_quantity' => 10,
    ]);

    app(GoldService::class)->addGold($student, 100, GoldTransaction::SOURCE_EVENT, 1, 'funding');
    $order = app(RedemptionService::class)->createOrder(
        $student,
        [['variant_id' => $variant->id, 'quantity' => 1]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    );

    $staff = User::factory()->create();
    grantRedemptionRoleAtCampus($staff, $campus, 'super_admin');

    session(['_token' => REDEMPTION_ACCESS_CSRF, 'current_campus_id' => $campus->id]);

    test()->actingAs($staff)
        ->post(route('redemption-orders.approve', $order), ['_token' => REDEMPTION_ACCESS_CSRF])
        ->assertOk();

    expect($order->fresh()->status)->toBe(RedemptionOrder::STATUS_APPROVED);
});

it('returns 403 for a parent proxy token on every merchandise student endpoint', function () {
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
        'status' => 'active',
    ]);

    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => 'Merchandise Test Guardian',
        'relationship_type' => 'parent',
        'email' => 'merch-guardian@example.test',
        'is_primary' => true,
    ]])[0];
    $grant = app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
    $parent = User::query()->findOrFail(ParentProfile::query()->findOrFail($grant->parentId)->user_id);

    $merchandise = Merchandise::factory()->create();
    $variant = MerchandiseVariant::factory()->create([
        'merchandise_id' => $merchandise->id,
        'campus_id' => $student->campus_id,
        'stock_quantity' => 5,
    ]);

    $order = RedemptionOrder::factory()->create(['student_id' => $student->id]);

    Sanctum::actingAs($parent);

    test()->getJson(route('v1.student.merchandise.store.index'))->assertForbidden();
    test()->getJson(route('v1.student.merchandise.store.show', $merchandise))->assertForbidden();
    test()->getJson(route('v1.student.merchandise.dashboard'))->assertForbidden();
    test()->postJson(route('v1.student.merchandise.orders.store'), [
        'lines' => [['variant_id' => $variant->id, 'quantity' => 1]],
        'method' => 'pickup',
    ])->assertForbidden();
    test()->getJson(route('v1.student.merchandise.orders.index'))->assertForbidden();
    test()->getJson(route('v1.student.merchandise.orders.show', $order))->assertForbidden();
    test()->postJson(route('v1.student.merchandise.orders.cancel', $order))->assertForbidden();
});
