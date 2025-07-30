<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Campus;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RoomService $service;
    protected Campus $campus;
    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RoomService();
        $this->campus = Campus::factory()->create([
            'name' => 'Test Campus',
            'code' => 'TC',
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
            'is_bookable' => true,
            'requires_approval' => false,
        ]);
    }

    /** @test */
    public function it_can_get_paginated_rooms_with_campus_scoping(): void
    {
        // Create rooms for different campuses
        $anotherCampus = Campus::factory()->create();
        Room::factory()->create(['campus_id' => $anotherCampus->id]);
        Room::factory()->create(['campus_id' => $this->campus->id]);

        $result = $this->service->getPaginatedRooms();

        expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
        expect($result->total())->toBe(2); // Only rooms from current campus
        expect($result->items())->toHaveCount(2);
        
        foreach ($result->items() as $room) {
            expect($room->campus_id)->toBe($this->campus->id);
        }
    }

    /** @test */
    public function it_can_apply_search_filter(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'name' => 'Conference Room',
            'code' => 'CR-001',
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'name' => 'Laboratory',
            'code' => 'LAB-001',
        ]);

        $filters = ['search' => 'Conference'];
        $result = $this->service->getPaginatedRooms($filters);

        expect($result->total())->toBe(1);
        expect($result->first()->name)->toBe('Conference Room');
    }

    /** @test */
    public function it_can_apply_type_filter(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'type' => Room::TYPE_LABORATORY,
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'type' => Room::TYPE_CLASSROOM,
        ]);

        $filters = ['type' => Room::TYPE_LABORATORY];
        $result = $this->service->getPaginatedRooms($filters);

        expect($result->total())->toBe(1);
        expect($result->first()->type)->toBe(Room::TYPE_LABORATORY);
    }

    /** @test */
    public function it_can_apply_status_filter(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'status' => Room::STATUS_MAINTENANCE,
        ]);

        $filters = ['status' => Room::STATUS_MAINTENANCE];
        $result = $this->service->getPaginatedRooms($filters);

        expect($result->total())->toBe(1);
        expect($result->first()->status)->toBe(Room::STATUS_MAINTENANCE);
    }

    /** @test */
    public function it_can_apply_building_filter(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'building' => 'Building B',
        ]);

        $filters = ['building' => 'Building B'];
        $result = $this->service->getPaginatedRooms($filters);

        expect($result->total())->toBe(1);
        expect($result->first()->building)->toBe('Building B');
    }

    /** @test */
    public function it_can_apply_bookable_filter(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'is_bookable' => false,
        ]);

        $filters = ['is_bookable' => true];
        $result = $this->service->getPaginatedRooms($filters);

        expect($result->total())->toBe(1);
        expect($result->first()->is_bookable)->toBe(true);
    }

    /** @test */
    public function it_can_apply_capacity_filters(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'capacity' => 25,
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'capacity' => 100,
        ]);

        $filters = ['min_capacity' => 30, 'max_capacity' => 80];
        $result = $this->service->getPaginatedRooms($filters);

        expect($result->total())->toBe(1);
        expect($result->first()->capacity)->toBe(50);
    }

    /** @test */
    public function it_can_apply_sorting(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'name' => 'Alpha Room',
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'name' => 'Beta Room',
        ]);

        $filters = ['sort' => 'name', 'direction' => 'asc'];
        $result = $this->service->getPaginatedRooms($filters);

        expect($result->first()->name)->toBe('Alpha Room');
        expect($result->last()->name)->toBe('Test Room'); // from setUp
    }

    /** @test */
    public function it_can_get_all_rooms_for_campus(): void
    {
        // Create rooms for different campuses
        $anotherCampus = Campus::factory()->create();
        Room::factory()->create(['campus_id' => $anotherCampus->id]);
        Room::factory()->create(['campus_id' => $this->campus->id]);

        $result = $this->service->getAllRooms();

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(2); // Only rooms from current campus
        
        foreach ($result as $room) {
            expect($room->campus_id)->toBe($this->campus->id);
        }
    }

    /** @test */
    public function it_can_find_room_by_id_with_campus_scoping(): void
    {
        $result = $this->service->findRoom($this->room->id);

        expect($result)->toBeInstanceOf(Room::class);
        expect($result->id)->toBe($this->room->id);
        expect($result->campus_id)->toBe($this->campus->id);
    }

    /** @test */
    public function it_returns_null_when_finding_room_from_different_campus(): void
    {
        $anotherCampus = Campus::factory()->create();
        $anotherRoom = Room::factory()->create(['campus_id' => $anotherCampus->id]);

        $result = $this->service->findRoom($anotherRoom->id);

        expect($result)->toBeNull();
    }

    /** @test */
    public function it_can_create_room_with_campus_association(): void
    {
        $data = [
            'name' => 'New Room',
            'code' => 'NR-001',
            'building' => 'Building C',
            'floor' => '2nd',
            'type' => Room::TYPE_LABORATORY,
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
            'requires_approval' => false,
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->createRoom($data);

        expect($result)->toBeInstanceOf(Room::class);
        expect($result->campus_id)->toBe($this->campus->id);
        expect($result->name)->toBe('New Room');
        expect($result->code)->toBe('NR-001');
    }

    /** @test */
    public function it_converts_blocked_days_json_string_to_array_when_creating(): void
    {
        $data = [
            'name' => 'New Room',
            'code' => 'NR-002',
            'building' => 'Building C',
            'floor' => '2nd',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
            'blocked_days' => json_encode(['Monday', 'Tuesday']),
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->createRoom($data);

        expect($result->blocked_days)->toBe(['Monday', 'Tuesday']);
    }

    /** @test */
    public function it_can_update_room(): void
    {
        $data = [
            'name' => 'Updated Room',
            'capacity' => 60,
            'status' => Room::STATUS_MAINTENANCE,
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->updateRoom($this->room, $data);

        expect($result)->toBeInstanceOf(Room::class);
        expect($result->name)->toBe('Updated Room');
        expect($result->capacity)->toBe(60);
        expect($result->status)->toBe(Room::STATUS_MAINTENANCE);
        expect($result->campus_id)->toBe($this->campus->id); // Campus should not change
    }

    /** @test */
    public function it_prevents_changing_campus_id_when_updating(): void
    {
        $anotherCampus = Campus::factory()->create();
        $data = [
            'campus_id' => $anotherCampus->id,
            'name' => 'Updated Room',
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->updateRoom($this->room, $data);

        expect($result->campus_id)->toBe($this->campus->id); // Original campus preserved
        expect($result->name)->toBe('Updated Room');
    }

    /** @test */
    public function it_converts_blocked_days_json_string_to_array_when_updating(): void
    {
        $data = [
            'blocked_days' => json_encode(['Friday', 'Saturday']),
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->updateRoom($this->room, $data);

        expect($result->blocked_days)->toBe(['Friday', 'Saturday']);
    }

    /** @test */
    public function it_can_delete_room(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->deleteRoom($this->room);

        expect($result)->toBe(true);
    }

    /** @test */
    public function it_can_get_rooms_by_type(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'type' => Room::TYPE_LABORATORY,
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'type' => Room::TYPE_LABORATORY,
        ]);

        $result = $this->service->getRoomsByType(Room::TYPE_LABORATORY);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(2);
        
        foreach ($result as $room) {
            expect($room->type)->toBe(Room::TYPE_LABORATORY);
            expect($room->campus_id)->toBe($this->campus->id);
        }
    }

    /** @test */
    public function it_can_get_available_rooms(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'status' => Room::STATUS_MAINTENANCE,
            'is_bookable' => true,
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => false,
        ]);

        $result = $this->service->getAvailableRooms();

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(2); // Original room + one new available/bookable room

        foreach ($result as $room) {
            expect($room->status)->toBe(Room::STATUS_AVAILABLE);
            expect($room->is_bookable)->toBe(true);
            expect($room->campus_id)->toBe($this->campus->id);
        }
    }

    /** @test */
    public function it_can_get_rooms_with_minimum_capacity(): void
    {
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'capacity' => 25,
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'capacity' => 75,
        ]);

        $result = $this->service->getRoomsWithMinimumCapacity(30);

        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(2); // Original room (50) + one new room (75)

        foreach ($result as $room) {
            expect($room->capacity)->toBeGreaterThanOrEqual(30);
            expect($room->campus_id)->toBe($this->campus->id);
        }
    }

    /** @test */
    public function it_can_get_room_statistics(): void
    {
        // Create rooms with different types and statuses
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'type' => Room::TYPE_LABORATORY,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
        ]);
        Room::factory()->create([
            'campus_id' => $this->campus->id,
            'type' => Room::TYPE_LABORATORY,
            'status' => Room::STATUS_MAINTENANCE,
            'is_bookable' => false,
        ]);

        $result = $this->service->getRoomStatistics();

        expect($result)->toHaveKeys(['total_rooms', 'available_rooms', 'bookable_rooms', 'by_type', 'by_status']);
        expect($result['total_rooms'])->toBe(3);
        expect($result['available_rooms'])->toBe(2);
        expect($result['bookable_rooms'])->toBe(2);
        expect($result['by_type'])->toHaveKeys([Room::TYPE_CLASSROOM, Room::TYPE_LABORATORY]);
        expect($result['by_type'][Room::TYPE_CLASSROOM])->toBe(1);
        expect($result['by_type'][Room::TYPE_LABORATORY])->toBe(2);
        expect($result['by_status'])->toHaveKeys([Room::STATUS_AVAILABLE, Room::STATUS_MAINTENANCE]);
        expect($result['by_status'][Room::STATUS_AVAILABLE])->toBe(2);
        expect($result['by_status'][Room::STATUS_MAINTENANCE])->toBe(1);
    }

    /** @test */
    public function it_only_includes_campus_rooms_in_statistics(): void
    {
        // Create rooms for different campuses
        $anotherCampus = Campus::factory()->create();
        Room::factory()->create([
            'campus_id' => $anotherCampus->id,
            'type' => Room::TYPE_LABORATORY,
        ]);

        $result = $this->service->getRoomStatistics();

        expect($result['total_rooms'])->toBe(1); // Only room from current campus
    }

    /** @test */
    public function it_handles_empty_blocked_days_correctly(): void
    {
        $data = [
            'name' => 'Room with No Blocked Days',
            'code' => 'NBD-001',
            'building' => 'Building D',
            'floor' => '1st',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 40,
            'status' => Room::STATUS_AVAILABLE,
            'blocked_days' => null,
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->createRoom($data);

        expect($result->blocked_days)->toBeNull();
    }

    /** @test */
    public function it_handles_invalid_json_in_blocked_days(): void
    {
        $data = [
            'name' => 'Room with Invalid JSON',
            'code' => 'IJD-001',
            'building' => 'Building E',
            'floor' => '1st',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 40,
            'status' => Room::STATUS_AVAILABLE,
            'blocked_days' => 'invalid-json',
        ];

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $result = $this->service->createRoom($data);

        expect($result->blocked_days)->toBe([]);
    }

    /** @test */
    public function it_respects_per_page_parameter_in_pagination(): void
    {
        // Create more rooms
        Room::factory()->count(5)->create(['campus_id' => $this->campus->id]);

        $result = $this->service->getPaginatedRooms([], 3);

        expect($result->perPage())->toBe(3);
        expect($result->items())->toHaveCount(3);
    }

    /** @test */
    public function it_orders_results_by_latest_by_default(): void
    {
        $oldRoom = Room::factory()->create([
            'campus_id' => $this->campus->id,
            'created_at' => now()->subDays(1),
        ]);

        $result = $this->service->getPaginatedRooms();

        expect($result->first()->id)->toBe($this->room->id); // Most recent room first
    }
}