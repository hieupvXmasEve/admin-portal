<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Merchandise\Models\Merchandise;
use App\Modules\Merchandise\Models\MerchandiseVariant;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const MERCHANDISE_CROSS_CAMPUS_CSRF = 'merchandise-cross-campus-test-csrf';

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);

    $this->campusA = Campus::factory()->create();
    $this->campusB = Campus::factory()->create();

    session(['_token' => MERCHANDISE_CROSS_CAMPUS_CSRF]);
});

function grantMerchandiseRoleAtCampus(User $user, Campus $campus, string $roleCode): void
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

it('forbids a staff member granted only at campus A from adjusting stock for a campus B variant', function () {
    $staff = User::factory()->create();
    grantMerchandiseRoleAtCampus($staff, $this->campusA, 'super_admin');

    $variant = MerchandiseVariant::factory()->create([
        'campus_id' => $this->campusB->id,
        'stock_quantity' => 10,
    ]);

    session(['current_campus_id' => $this->campusA->id]);

    $response = $this->actingAs($staff)->post(route('merchandise.variants.stock-adjust', $variant), [
        '_token' => MERCHANDISE_CROSS_CAMPUS_CSRF,
        'change' => -1,
        'type' => 'manual_decrease',
    ]);

    $response->assertForbidden();
    expect($variant->fresh()->stock_quantity)->toBe(10);
});

it('forbids editing a campus B variant from a campus A grant', function () {
    $staff = User::factory()->create();
    grantMerchandiseRoleAtCampus($staff, $this->campusA, 'super_admin');

    $variant = MerchandiseVariant::factory()->create(['campus_id' => $this->campusB->id]);

    session(['current_campus_id' => $this->campusA->id]);

    $response = $this->actingAs($staff)->put(route('merchandise.variants.update', $variant), [
        '_token' => MERCHANDISE_CROSS_CAMPUS_CSRF,
        'color' => 'red',
    ]);

    $response->assertForbidden();
});

it('forbids creating a variant at a campus the staff member is not granted at', function () {
    $staff = User::factory()->create();
    grantMerchandiseRoleAtCampus($staff, $this->campusA, 'super_admin');

    $merchandise = Merchandise::factory()->create();

    session(['current_campus_id' => $this->campusA->id]);

    $response = $this->actingAs($staff)->post(route('merchandise.variants.store', $merchandise), [
        '_token' => MERCHANDISE_CROSS_CAMPUS_CSRF,
        'campus_id' => $this->campusB->id,
        'stock_quantity' => 5,
    ]);

    $response->assertForbidden();
    expect(MerchandiseVariant::where('campus_id', $this->campusB->id)->count())->toBe(0);
});

it('allows a staff member granted at the variant campus to adjust stock', function () {
    $staff = User::factory()->create();
    grantMerchandiseRoleAtCampus($staff, $this->campusB, 'super_admin');

    $variant = MerchandiseVariant::factory()->create([
        'campus_id' => $this->campusB->id,
        'stock_quantity' => 10,
    ]);

    session(['current_campus_id' => $this->campusB->id]);

    $response = $this->actingAs($staff)->post(route('merchandise.variants.stock-adjust', $variant), [
        '_token' => MERCHANDISE_CROSS_CAMPUS_CSRF,
        'change' => -1,
        'type' => 'manual_decrease',
    ]);

    $response->assertOk();
    expect($variant->fresh()->stock_quantity)->toBe(9);
});
