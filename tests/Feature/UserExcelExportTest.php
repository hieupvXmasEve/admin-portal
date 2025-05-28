<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Campus;
use App\Models\Role;
use App\Models\CampusUserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_export_excel(): void
    {
        // Create test data
        $user = User::factory()->create();
        $campus = Campus::factory()->create();
        $role = Role::factory()->create();

        // Create campus user role relationship
        CampusUserRole::create([
            'user_id' => $user->id,
            'campus_id' => $campus->id,
            'role_id' => $role->id,
        ]);

        // Act as authenticated user with session
        $this->actingAs($user);

        // Set current campus and permissions in session
        session([
            'current_campus_id' => $campus->id,
            'permissions' => collect(['view_user']) // Add the required permission
        ]);

        // Make export request
        $response = $this->get(route('users.export.excel'));

        // Debug the response if it's not 200
        if ($response->getStatusCode() !== 200) {
            dump('Response status: ' . $response->getStatusCode());
            dump('Response headers: ' . json_encode($response->headers->all()));
            dump('Response content: ' . $response->getContent());
        }

        // Assert successful response
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_unauthenticated_user_cannot_export_excel(): void
    {
        // Make export request without authentication
        $response = $this->get(route('users.export.excel'));

        // Assert redirect to login
        $response->assertRedirect(route('login'));
    }

    public function test_export_with_filters_works(): void
    {
        // Create test data
        $user = User::factory()->create(['name' => 'John Doe']);
        $campus = Campus::factory()->create();
        $role = Role::factory()->create();

        CampusUserRole::create([
            'user_id' => $user->id,
            'campus_id' => $campus->id,
            'role_id' => $role->id,
        ]);

        // Act as authenticated user
        $this->actingAs($user);

        // Set permissions in session
        session([
            'current_campus_id' => $campus->id,
            'permissions' => collect(['view_user'])
        ]);

        // Make export request with filters
        $response = $this->get(route('users.export.excel.filtered', [
            'search' => 'John'
        ]));

        // Assert successful response
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
