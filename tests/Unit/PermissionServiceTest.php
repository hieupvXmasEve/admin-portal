<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Campus;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PermissionService();
    }

    public function test_get_user_permissions_from_cache()
    {
        // Arrange
        $user = User::factory()->create();
        $campusId = 1;
        $expectedPermissions = ['view_student', 'edit_student'];

        // Pre-populate cache
        $cacheKey = "user_permissions_{$user->id}_campus_{$campusId}";
        Cache::put($cacheKey, $expectedPermissions, now()->addDay());

        // Act
        $permissions = $this->service->getUserPermissions($user, $campusId);

        // Assert
        $this->assertEquals($expectedPermissions, $permissions);
    }

    public function test_get_user_permissions_from_database_and_cache()
    {
        // Arrange
        $user = User::factory()->create();
        $campus = Campus::factory()->create();
        $role = Role::factory()->create();
        $permission1 = Permission::factory()->create(['name' => 'view_student']);
        $permission2 = Permission::factory()->create(['name' => 'edit_student']);

        // Assign permissions to role
        $role->permissions()->attach([$permission1->id, $permission2->id]);

        // Assign role to user at campus
        DB::table('campus_user_roles')->insert([
            'user_id' => $user->id,
            'campus_id' => $campus->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Act
        $permissions = $this->service->getUserPermissions($user, $campus->id);

        // Assert
        $this->assertContains('view_student', $permissions);
        $this->assertContains('edit_student', $permissions);

        // Verify cache was set
        $cacheKey = "user_permissions_{$user->id}_campus_{$campus->id}";
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_clear_user_permissions_cache()
    {
        // Arrange
        $user = User::factory()->create();
        $campus1 = Campus::factory()->create();
        $campus2 = Campus::factory()->create();
        $role = Role::factory()->create();

        // Create campus-user-role relationships
        DB::table('campus_user_roles')->insert([
            ['user_id' => $user->id, 'campus_id' => $campus1->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'campus_id' => $campus2->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Pre-populate cache
        Cache::put("user_permissions_{$user->id}_campus_all", ['permission1'], now()->addDay());
        Cache::put("user_permissions_{$user->id}_campus_{$campus1->id}", ['permission2'], now()->addDay());
        Cache::put("user_permissions_{$user->id}_campus_{$campus2->id}", ['permission3'], now()->addDay());

        // Act
        $this->service->clearUserPermissionsCache($user);

        // Assert
        $this->assertFalse(Cache::has("user_permissions_{$user->id}_campus_all"));
        $this->assertFalse(Cache::has("user_permissions_{$user->id}_campus_{$campus1->id}"));
        $this->assertFalse(Cache::has("user_permissions_{$user->id}_campus_{$campus2->id}"));
    }
}
