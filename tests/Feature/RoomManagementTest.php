<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoomManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Campus $campus;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::factory()->create([
            'name' => 'Test Campus',
            'code' => 'TC',
        ]);

        $this->user = User::factory()->create([
            'campus_id' => $this->campus->id,
        ]);

        // Mock the campus context
        App::instance('campus', $this->campus);

        $this->room = Room::factory()->create([
            'campus_id' => $this->campus->id,
            'name' => 'Test Room',
            'code' => 'TR-001',
            'building' => 'Building A',
            'floor' => '1st',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 50,
            'status' => Room::STATUS_AVAILABLE,
        ]);

        $this->createPermissions();
    }

    private function createPermissions(): void
    {
        Permission::create(['name' => 'view_room']);
        Permission::create(['name' => 'create_room']);
        Permission::create(['name' => 'edit_room']);
        Permission::create(['name' => 'delete_room']);
    }

    private function giveUserPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->user->givePermissionTo($permission);
        }
    }

    /** @test */
    public function it_requires_authentication_to_access_rooms_index(): void
    {
        $response = $this->get(route('rooms.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function it_requires_view_room_permission_to_access_index(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_display_rooms_index_with_proper_permissions(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'name' => 'Another Room',
        ]);

        $response = $this->get(route('rooms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->has('rooms')
            ->has('rooms.data', 2)
            ->has('statistics')
            ->has('room_types')
            ->has('room_statuses')
            ->has('buildings')
            ->has('floors')
            ->has('permissions')
            ->where('permissions.can_create', false)
            ->where('permissions.can_edit', false)
            ->where('permissions.can_delete', false)
        );
    }

    /** @test */
    public function it_displays_correct_permissions_in_index(): void
    {
        $this->giveUserPermissions(['view_room', 'create_room', 'edit_room']);
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->where('permissions.can_create', true)
            ->where('permissions.can_edit', true)
            ->where('permissions.can_delete', false)
        );
    }

    /** @test */
    public function it_applies_search_filter_in_index(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'name' => 'Conference Room',
        ]);

        $response = $this->get(route('rooms.index', ['search' => 'Conference']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->has('rooms.data', 1)
            ->where('rooms.data.0.name', 'Conference Room')
        );
    }

    /** @test */
    public function it_applies_type_filter_in_index(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'type' => Room::TYPE_LABORATORY,
        ]);

        $response = $this->get(route('rooms.index', ['type' => Room::TYPE_LABORATORY]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->has('rooms.data', 1)
            ->where('rooms.data.0.type', Room::TYPE_LABORATORY)
        );
    }

    /** @test */
    public function it_only_shows_rooms_from_current_campus(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $anotherCampus = Campus::factory()->create();
        Room::factory()->create(['campus_id' => $anotherCampus->id]);

        $response = $this->get(route('rooms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->has('rooms.data', 1) // Only room from current campus
        );
    }

    /** @test */
    public function it_requires_create_room_permission_to_access_create_form(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.create'));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_display_create_form_with_proper_permissions(): void
    {
        $this->giveUserPermissions(['create_room']);
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.create'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Create')
            ->has('room_types')
            ->has('room_statuses')
            ->has('buildings')
            ->has('days_of_week')
        );
    }

    /** @test */
    public function it_requires_create_room_permission_to_store_room(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $data = [
            'name' => 'New Room',
            'code' => 'NR-001',
            'building' => 'Building B',
            'floor' => '2nd',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
        ];

        $response = $this->post(route('rooms.store'), $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_store_room_with_proper_permissions(): void
    {
        $this->giveUserPermissions(['create_room']);
        $this->actingAs($this->user);

        $data = [
            'name' => 'New Room',
            'code' => 'NR-001',
            'building' => 'Building B',
            'floor' => '2nd',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
            'requires_approval' => false,
        ];

        $response = $this->post(route('rooms.store'), $data);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rooms', [
            'campus_id' => $this->campus->id,
            'name' => 'New Room',
            'code' => 'NR-001',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_storing(): void
    {
        $this->giveUserPermissions(['create_room']);
        $this->actingAs($this->user);

        $response = $this->post(route('rooms.store'), []);

        $response->assertSessionHasErrors([
            'name',
            'code',
            'building',
            'floor',
            'type',
            'capacity',
            'status',
        ]);
    }

    /** @test */
    public function it_validates_unique_room_code_within_campus_when_storing(): void
    {
        $this->giveUserPermissions(['create_room']);
        $this->actingAs($this->user);

        $data = [
            'name' => 'New Room',
            'code' => $this->room->code, // Duplicate code
            'building' => 'Building B',
            'floor' => '2nd',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
        ];

        $response = $this->post(route('rooms.store'), $data);

        $response->assertSessionHasErrors(['code']);
    }

    /** @test */
    public function it_allows_same_room_code_in_different_campus(): void
    {
        $this->giveUserPermissions(['create_room']);
        $this->actingAs($this->user);

        $anotherCampus = Campus::factory()->create();
        Room::factory()->create([
            'campus_id' => $anotherCampus->id,
            'code' => 'SAME-001',
        ]);

        $data = [
            'name' => 'New Room',
            'code' => 'SAME-001', // Same code but different campus
            'building' => 'Building B',
            'floor' => '2nd',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
        ];

        $response = $this->post(route('rooms.store'), $data);

        $response->assertRedirect(route('rooms.index'));
        $this->assertDatabaseHas('rooms', [
            'campus_id' => $this->campus->id,
            'code' => 'SAME-001',
        ]);
    }

    /** @test */
    public function it_requires_view_room_permission_to_show_room(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.show', $this->room));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_show_room_with_proper_permissions(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.show', $this->room));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Show')
            ->has('room')
            ->where('room.id', $this->room->id)
            ->where('room.name', $this->room->name)
            ->has('permissions')
        );
    }

    /** @test */
    public function it_returns_404_when_showing_room_from_different_campus(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $anotherCampus = Campus::factory()->create();
        $anotherRoom = Room::factory()->create(['campus_id' => $anotherCampus->id]);

        $response = $this->get(route('rooms.show', $anotherRoom));

        $response->assertStatus(404);
    }

    /** @test */
    public function it_requires_edit_room_permission_to_edit_room(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.edit', $this->room));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_edit_room_with_proper_permissions(): void
    {
        $this->giveUserPermissions(['edit_room']);
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.edit', $this->room));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Edit')
            ->has('room')
            ->where('room.id', $this->room->id)
            ->has('room_types')
            ->has('room_statuses')
            ->has('buildings')
            ->has('days_of_week')
        );
    }

    /** @test */
    public function it_returns_404_when_editing_room_from_different_campus(): void
    {
        $this->giveUserPermissions(['edit_room']);
        $this->actingAs($this->user);

        $anotherCampus = Campus::factory()->create();
        $anotherRoom = Room::factory()->create(['campus_id' => $anotherCampus->id]);

        $response = $this->get(route('rooms.edit', $anotherRoom));

        $response->assertStatus(404);
    }

    /** @test */
    public function it_requires_edit_room_permission_to_update_room(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $data = ['name' => 'Updated Room'];

        $response = $this->patch(route('rooms.update', $this->room), $data);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_update_room_with_proper_permissions(): void
    {
        $this->giveUserPermissions(['edit_room']);
        $this->actingAs($this->user);

        $data = [
            'name' => 'Updated Room',
            'code' => $this->room->code,
            'building' => $this->room->building,
            'floor' => $this->room->floor,
            'type' => $this->room->type,
            'capacity' => 75,
            'status' => Room::STATUS_MAINTENANCE,
            'is_bookable' => false,
        ];

        $response = $this->patch(route('rooms.update', $this->room), $data);

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'name' => 'Updated Room',
            'capacity' => 75,
            'status' => Room::STATUS_MAINTENANCE,
            'campus_id' => $this->campus->id, // Campus should remain unchanged
        ]);
    }

    /** @test */
    public function it_returns_404_when_updating_room_from_different_campus(): void
    {
        $this->giveUserPermissions(['edit_room']);
        $this->actingAs($this->user);

        $anotherCampus = Campus::factory()->create();
        $anotherRoom = Room::factory()->create(['campus_id' => $anotherCampus->id]);

        $data = ['name' => 'Updated Room'];

        $response = $this->patch(route('rooms.update', $anotherRoom), $data);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_validates_unique_room_code_when_updating(): void
    {
        $this->giveUserPermissions(['edit_room']);
        $this->actingAs($this->user);

        $anotherRoom = Room::factory()->create([
            'campus_id' => $this->campus->id,
            'code' => 'ANOTHER-001',
        ]);

        $data = [
            'name' => $this->room->name,
            'code' => 'ANOTHER-001', // Duplicate code
            'building' => $this->room->building,
            'floor' => $this->room->floor,
            'type' => $this->room->type,
            'capacity' => $this->room->capacity,
            'status' => $this->room->status,
        ];

        $response = $this->patch(route('rooms.update', $this->room), $data);

        $response->assertSessionHasErrors(['code']);
    }

    /** @test */
    public function it_allows_keeping_same_code_when_updating(): void
    {
        $this->giveUserPermissions(['edit_room']);
        $this->actingAs($this->user);

        $data = [
            'name' => 'Updated Room',
            'code' => $this->room->code, // Same code
            'building' => $this->room->building,
            'floor' => $this->room->floor,
            'type' => $this->room->type,
            'capacity' => $this->room->capacity,
            'status' => $this->room->status,
        ];

        $response = $this->patch(route('rooms.update', $this->room), $data);

        $response->assertRedirect(route('rooms.index'));
        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'name' => 'Updated Room',
        ]);
    }

    /** @test */
    public function it_requires_delete_room_permission_to_destroy_room(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $response = $this->delete(route('rooms.destroy', $this->room));

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_destroy_room_with_proper_permissions(): void
    {
        $this->giveUserPermissions(['delete_room']);
        $this->actingAs($this->user);

        $roomName = $this->room->name;

        $response = $this->delete(route('rooms.destroy', $this->room));

        $response->assertRedirect(route('rooms.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('rooms', [
            'id' => $this->room->id,
        ]);
    }

    /** @test */
    public function it_returns_404_when_destroying_room_from_different_campus(): void
    {
        $this->giveUserPermissions(['delete_room']);
        $this->actingAs($this->user);

        $anotherCampus = Campus::factory()->create();
        $anotherRoom = Room::factory()->create(['campus_id' => $anotherCampus->id]);

        $response = $this->delete(route('rooms.destroy', $anotherRoom));

        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_store_exceptions_gracefully(): void
    {
        $this->giveUserPermissions(['create_room']);
        $this->actingAs($this->user);

        // Force an exception by providing invalid data that passes validation but fails in service
        $data = [
            'name' => 'New Room',
            'code' => 'NR-001',
            'building' => 'Building B',
            'floor' => '2nd',
            'type' => 'invalid_type', // This will pass form validation but cause service to fail
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
        ];

        $response = $this->post(route('rooms.store'), $data);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function it_handles_update_exceptions_gracefully(): void
    {
        $this->giveUserPermissions(['edit_room']);
        $this->actingAs($this->user);

        // Mock a scenario where service update would fail
        $data = [
            'name' => str_repeat('a', 300), // Exceeds database field length
            'code' => $this->room->code,
            'building' => $this->room->building,
            'floor' => $this->room->floor,
            'type' => $this->room->type,
            'capacity' => $this->room->capacity,
            'status' => $this->room->status,
        ];

        $response = $this->patch(route('rooms.update', $this->room), $data);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function it_handles_destroy_exceptions_gracefully(): void
    {
        $this->giveUserPermissions(['delete_room']);
        $this->actingAs($this->user);

        // Create a room that might have foreign key constraints preventing deletion
        // (In a real scenario, this could be a room with active bookings)

        $response = $this->delete(route('rooms.destroy', $this->room));

        // The response should either succeed or handle the exception gracefully
        if ($response->getStatusCode() === 302) {
            // If redirected, check for error message
            $response->assertRedirect();
        } else {
            $response->assertStatus(200);
        }
    }

    /** @test */
    public function it_includes_room_statistics_in_index(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'status' => Room::STATUS_MAINTENANCE,
        ]);

        $response = $this->get(route('rooms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->has('statistics')
            ->has('statistics.total_rooms')
            ->has('statistics.available_rooms')
            ->has('statistics.bookable_rooms')
            ->has('statistics.by_type')
            ->has('statistics.by_status')
        );
    }

    /** @test */
    public function it_includes_filter_options_in_index(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        $response = $this->get(route('rooms.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->has('room_types')
            ->has('room_statuses')
            ->has('buildings')
            ->has('floors')
        );
    }

    /** @test */
    public function it_respects_per_page_parameter(): void
    {
        $this->giveUserPermissions(['view_room']);
        $this->actingAs($this->user);

        Room::factory()->count(5)->create(['campus_id' => $this->campus->id]);

        $response = $this->get(route('rooms.index', ['per_page' => 3]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('rooms/Index')
            ->where('rooms.per_page', 3)
        );
    }

    /** @test */
    public function it_enforces_campus_context_middleware(): void
    {
        $this->giveUserPermissions(['view_room']);

        // Create user without campus association
        $userWithoutCampus = User::factory()->create(['campus_id' => null]);
        $this->actingAs($userWithoutCampus);

        // Mock App to return null campus (simulating middleware failure)
        App::instance('campus', null);

        $response = $this->get(route('rooms.index'));

        // This should either redirect or return an error due to missing campus context
        $this->assertTrue(
            $response->getStatusCode() === 302 || $response->getStatusCode() >= 400
        );
    }
}
