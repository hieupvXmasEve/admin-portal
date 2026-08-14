<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use App\Modules\Merchandise\Models\Merchandise;
use App\Modules\Merchandise\Models\MerchandiseVariant;
use App\Modules\Merchandise\Models\StockMovement;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const MERCHANDISE_CRUD_CSRF = 'merchandise-crud-test-csrf';

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);

    $this->campus = Campus::factory()->create();
    $this->app->singleton('campus', fn () => $this->campus);
    session([
        '_token' => MERCHANDISE_CRUD_CSRF,
        'current_campus_id' => $this->campus->id,
    ]);

    $this->admin = User::factory()->create();
    $role = Role::where('code', 'super_admin')->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $this->admin->id,
        'campus_id' => $this->campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $this->admin->id);
});

function withMerchandiseCsrf(array $payload = []): array
{
    return array_merge(['_token' => MERCHANDISE_CRUD_CSRF], $payload);
}

it('creates merchandise', function () {
    $response = $this->actingAs($this->admin)->post(route('merchandise.store'), withMerchandiseCsrf([
        'name' => 'Hoodie',
        'description' => 'Warm hoodie',
        'gold_price' => 500,
        'status' => 'active',
    ]));

    $response->assertCreated();
    $this->assertDatabaseHas('merchandise', ['name' => 'Hoodie', 'gold_price' => 500, 'status' => 'active']);
});

it('rejects merchandise creation with an unknown status', function () {
    $response = $this->actingAs($this->admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post(route('merchandise.store'), withMerchandiseCsrf([
            'name' => 'Hoodie',
            'gold_price' => 500,
            'status' => 'not_a_real_status',
        ]));

    $response->assertUnprocessable();
});

it('updates merchandise', function () {
    $merchandise = Merchandise::factory()->create(['gold_price' => 100]);

    $response = $this->actingAs($this->admin)->put(route('merchandise.update', $merchandise), withMerchandiseCsrf([
        'name' => $merchandise->name,
        'gold_price' => 250,
        'status' => 'active',
    ]));

    $response->assertOk();
    expect($merchandise->fresh()->gold_price)->toBe(250);
});

it('archives merchandise instead of deleting it', function () {
    $merchandise = Merchandise::factory()->create(['status' => 'active']);

    $response = $this->actingAs($this->admin)->post(route('merchandise.archive', $merchandise), withMerchandiseCsrf());

    $response->assertOk();
    expect($merchandise->fresh()->status)->toBe(Merchandise::STATUS_ARCHIVED);
    $this->assertDatabaseHas('merchandise', ['id' => $merchandise->id]);
});

it('creates a variant scoped to a campus', function () {
    $merchandise = Merchandise::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('merchandise.variants.store', $merchandise), withMerchandiseCsrf([
        'campus_id' => $this->campus->id,
        'color' => 'black',
        'size' => 'M',
        'stock_quantity' => 10,
    ]));

    $response->assertCreated();
    $this->assertDatabaseHas('merchandise_variants', [
        'merchandise_id' => $merchandise->id,
        'campus_id' => $this->campus->id,
        'color' => 'black',
        'size' => 'M',
        'stock_quantity' => 10,
    ]);
});

it('updates a variant without touching stock_quantity', function () {
    $variant = MerchandiseVariant::factory()->create([
        'campus_id' => $this->campus->id,
        'stock_quantity' => 7,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->put(route('merchandise.variants.update', $variant), withMerchandiseCsrf([
        'color' => 'red',
        'is_active' => false,
        'stock_quantity' => 999, // not a mutable field via this endpoint
    ]));

    $response->assertOk();
    $fresh = $variant->fresh();
    expect($fresh->color)->toBe('red')
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->stock_quantity)->toBe(7);
});

it('adjusts variant stock and writes a movement with correct before/after', function () {
    $variant = MerchandiseVariant::factory()->create([
        'campus_id' => $this->campus->id,
        'stock_quantity' => 5,
    ]);

    $response = $this->actingAs($this->admin)->post(route('merchandise.variants.stock-adjust', $variant), withMerchandiseCsrf([
        'change' => -3,
        'type' => StockMovement::TYPE_MANUAL_DECREASE,
        'note' => 'damaged units',
    ]));

    $response->assertOk();
    expect($variant->fresh()->stock_quantity)->toBe(2);
    $this->assertDatabaseHas('stock_movements', [
        'merchandise_variant_id' => $variant->id,
        'change' => -3,
        'quantity_before' => 5,
        'quantity_after' => 2,
        'type' => 'manual_decrease',
        'performed_by' => $this->admin->id,
    ]);
});

it('rejects a stock adjustment that would go negative and leaves the row unchanged', function () {
    $variant = MerchandiseVariant::factory()->create([
        'campus_id' => $this->campus->id,
        'stock_quantity' => 2,
    ]);

    $response = $this->actingAs($this->admin)->post(route('merchandise.variants.stock-adjust', $variant), withMerchandiseCsrf([
        'change' => -5,
        'type' => StockMovement::TYPE_MANUAL_DECREASE,
    ]));

    $response->assertUnprocessable();
    expect($variant->fresh()->stock_quantity)->toBe(2);
    expect(StockMovement::where('merchandise_variant_id', $variant->id)->count())->toBe(0);
});

it('reads the stock movement history for a variant', function () {
    $variant = MerchandiseVariant::factory()->create([
        'campus_id' => $this->campus->id,
        'stock_quantity' => 5,
    ]);

    $this->actingAs($this->admin)->post(route('merchandise.variants.stock-adjust', $variant), withMerchandiseCsrf([
        'change' => 10,
        'type' => StockMovement::TYPE_STOCK_IN,
    ]));

    $response = $this->actingAs($this->admin)->get(route('merchandise.variants.stock-movements', $variant));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});
