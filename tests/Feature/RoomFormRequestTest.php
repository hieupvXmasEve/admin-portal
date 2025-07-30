<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Campus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoomFormRequestTest extends TestCase
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
            'code' => 'EXISTING-001',
        ]);

        $this->createPermissions();
    }

    private function createPermissions(): void
    {
        Permission::create(['name' => 'create_room']);
        Permission::create(['name' => 'edit_room']);
    }

    // StoreRoomRequest Tests

    /** @test */
    public function it_requires_create_room_permission_for_authorization(): void
    {
        $request = new StoreRoomRequest();
        $request->setUserResolver(fn () => $this->user);

        expect($request->authorize())->toBe(false);

        $this->user->givePermissionTo('create_room');

        expect($request->authorize())->toBe(true);
    }

    /** @test */
    public function it_requires_name_field(): void
    {
        $data = $this->getValidStoreData(['name' => null]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('name'))->toBe(true);
    }

    /** @test */
    public function it_validates_name_is_string_and_max_length(): void
    {
        $data = $this->getValidStoreData(['name' => 123]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('name'))->toBe(true);

        $data = $this->getValidStoreData(['name' => str_repeat('a', 256)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('name'))->toBe(true);

        $data = $this->getValidStoreData(['name' => str_repeat('a', 255)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_requires_code_field(): void
    {
        $data = $this->getValidStoreData(['code' => null]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('code'))->toBe(true);
    }

    /** @test */
    public function it_validates_code_is_string_and_max_length(): void
    {
        $data = $this->getValidStoreData(['code' => 123]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('code'))->toBe(true);

        $data = $this->getValidStoreData(['code' => str_repeat('a', 51)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('code'))->toBe(true);

        $data = $this->getValidStoreData(['code' => str_repeat('a', 50)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_validates_code_uniqueness_within_campus(): void
    {
        $data = $this->getValidStoreData(['code' => $this->room->code]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('code'))->toBe(true);
        expect($validator->errors()->first('code'))->toContain('already exists');
    }

    /** @test */
    public function it_allows_same_code_in_different_campus(): void
    {
        $anotherCampus = Campus::factory()->create();
        Room::factory()->create([
            'campus_id' => $anotherCampus->id,
            'code' => 'SAME-CODE',
        ]);

        $data = $this->getValidStoreData(['code' => 'SAME-CODE']);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_requires_building_field(): void
    {
        $data = $this->getValidStoreData(['building' => null]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('building'))->toBe(true);
    }

    /** @test */
    public function it_validates_building_is_string_and_max_length(): void
    {
        $data = $this->getValidStoreData(['building' => str_repeat('a', 256)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('building'))->toBe(true);

        $data = $this->getValidStoreData(['building' => str_repeat('a', 255)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_requires_floor_field(): void
    {
        $data = $this->getValidStoreData(['floor' => null]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('floor'))->toBe(true);
    }

    /** @test */
    public function it_validates_floor_is_string_and_max_length(): void
    {
        $data = $this->getValidStoreData(['floor' => str_repeat('a', 51)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('floor'))->toBe(true);

        $data = $this->getValidStoreData(['floor' => str_repeat('a', 50)]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_requires_type_field(): void
    {
        $data = $this->getValidStoreData(['type' => null]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('type'))->toBe(true);
    }

    /** @test */
    public function it_validates_type_is_in_allowed_types(): void
    {
        $data = $this->getValidStoreData(['type' => 'invalid_type']);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('type'))->toBe(true);
        expect($validator->errors()->first('type'))->toContain('invalid');

        foreach (Room::getTypes() as $type) {
            $data = $this->getValidStoreData(['type' => $type]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);
        }
    }

    /** @test */
    public function it_requires_capacity_field(): void
    {
        $data = $this->getValidStoreData(['capacity' => null]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('capacity'))->toBe(true);
    }

    /** @test */
    public function it_validates_capacity_is_integer_with_min_max(): void
    {
        $data = $this->getValidStoreData(['capacity' => 'not_integer']);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('capacity'))->toBe(true);

        $data = $this->getValidStoreData(['capacity' => 0]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('capacity'))->toBe(true);

        $data = $this->getValidStoreData(['capacity' => 10001]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('capacity'))->toBe(true);

        $data = $this->getValidStoreData(['capacity' => 1]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);

        $data = $this->getValidStoreData(['capacity' => 10000]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_requires_status_field(): void
    {
        $data = $this->getValidStoreData(['status' => null]);

        $validator = Validator::make($data, (new StoreRoomRequest())->rules());

        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('status'))->toBe(true);
    }

    /** @test */
    public function it_validates_status_is_in_allowed_statuses(): void
    {
        $data = $this->getValidStoreData(['status' => 'invalid_status']);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('status'))->toBe(true);
        expect($validator->errors()->first('status'))->toContain('invalid');

        foreach (Room::getStatuses() as $status) {
            $data = $this->getValidStoreData(['status' => $status]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);
        }
    }

    /** @test */
    public function it_validates_boolean_fields(): void
    {
        $booleanFields = ['is_bookable', 'requires_approval'];

        foreach ($booleanFields as $field) {
            $data = $this->getValidStoreData([$field => 'not_boolean']);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->fails())->toBe(true);
            expect($validator->errors()->has($field))->toBe(true);

            $data = $this->getValidStoreData([$field => true]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);

            $data = $this->getValidStoreData([$field => false]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);
        }
    }

    /** @test */
    public function it_validates_time_format_for_availability_times(): void
    {
        $timeFields = ['available_from', 'available_until'];

        foreach ($timeFields as $field) {
            $data = $this->getValidStoreData([$field => 'invalid_time']);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->fails())->toBe(true);
            expect($validator->errors()->has($field))->toBe(true);
            expect($validator->errors()->first($field))->toContain('HH:MM:SS');

            $data = $this->getValidStoreData([$field => '09:00:00']);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);

            $data = $this->getValidStoreData([$field => null]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);
        }
    }

    /** @test */
    public function it_validates_blocked_days_array(): void
    {
        $data = $this->getValidStoreData(['blocked_days' => 'not_array']);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('blocked_days'))->toBe(true);

        $data = $this->getValidStoreData(['blocked_days' => ['InvalidDay']]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('blocked_days.0'))->toBe(true);

        $data = $this->getValidStoreData(['blocked_days' => ['Monday', 'Tuesday']]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);

        $data = $this->getValidStoreData(['blocked_days' => null]);
        $validator = Validator::make($data, (new StoreRoomRequest())->rules());
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_validates_optional_text_fields_max_length(): void
    {
        $textFields = [
            'description' => 1000,
            'usage_guidelines' => 2000,
            'booking_notes' => 1000,
        ];

        foreach ($textFields as $field => $maxLength) {
            $data = $this->getValidStoreData([$field => str_repeat('a', $maxLength + 1)]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->fails())->toBe(true);
            expect($validator->errors()->has($field))->toBe(true);

            $data = $this->getValidStoreData([$field => str_repeat('a', $maxLength)]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);

            $data = $this->getValidStoreData([$field => null]);
            $validator = Validator::make($data, (new StoreRoomRequest())->rules());
            expect($validator->passes())->toBe(true);
        }
    }

    /** @test */
    public function it_has_custom_attribute_names(): void
    {
        $request = new StoreRoomRequest();
        $attributes = $request->attributes();

        expect($attributes)->toHaveKey('name');
        expect($attributes['name'])->toBe('room name');
        expect($attributes)->toHaveKey('code');
        expect($attributes['code'])->toBe('room code');
        expect($attributes)->toHaveKey('type');
        expect($attributes['type'])->toBe('room type');
    }

    /** @test */
    public function it_has_custom_validation_messages(): void
    {
        $request = new StoreRoomRequest();
        $messages = $request->messages();

        expect($messages)->toHaveKey('code.unique');
        expect($messages['code.unique'])->toContain('already exists in the current campus');
        expect($messages)->toHaveKey('type.in');
        expect($messages['type.in'])->toContain('invalid');
        expect($messages)->toHaveKey('available_from.date_format');
        expect($messages['available_from.date_format'])->toContain('HH:MM:SS');
    }

    // UpdateRoomRequest Tests

    /** @test */
    public function it_requires_edit_room_permission_for_update_authorization(): void
    {
        $request = new UpdateRoomRequest();
        $request->setUserResolver(fn () => $this->user);

        expect($request->authorize())->toBe(false);

        $this->user->givePermissionTo('edit_room');

        expect($request->authorize())->toBe(true);
    }

    /** @test */
    public function it_validates_code_uniqueness_ignoring_current_room(): void
    {
        $anotherRoom = Room::factory()->create([
            'campus_id' => $this->campus->id,
            'code' => 'ANOTHER-001',
        ]);

        // Should fail when using another room's code
        $data = $this->getValidUpdateData(['code' => 'ANOTHER-001']);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('code'))->toBe(true);

        // Should pass when keeping the same code
        $data = $this->getValidUpdateData(['code' => $this->room->code]);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_validates_all_required_fields_for_update(): void
    {
        $requiredFields = ['name', 'code', 'building', 'floor', 'type', 'capacity', 'status'];

        foreach ($requiredFields as $field) {
            $data = $this->getValidUpdateData([$field => null]);
            $validator = $this->getUpdateValidator($data, $this->room);
            expect($validator->fails())->toBe(true);
            expect($validator->errors()->has($field))->toBe(true);
        }
    }

    /** @test */
    public function it_validates_field_constraints_for_update(): void
    {
        // Test name max length
        $data = $this->getValidUpdateData(['name' => str_repeat('a', 256)]);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('name'))->toBe(true);

        // Test capacity range
        $data = $this->getValidUpdateData(['capacity' => 0]);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('capacity'))->toBe(true);

        $data = $this->getValidUpdateData(['capacity' => 10001]);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('capacity'))->toBe(true);

        // Test valid type
        $data = $this->getValidUpdateData(['type' => 'invalid_type']);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('type'))->toBe(true);

        // Test valid status
        $data = $this->getValidUpdateData(['status' => 'invalid_status']);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('status'))->toBe(true);
    }

    /** @test */
    public function it_validates_time_format_for_availability_times_in_update(): void
    {
        $data = $this->getValidUpdateData(['available_from' => 'invalid_time']);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('available_from'))->toBe(true);

        $data = $this->getValidUpdateData(['available_until' => '25:00:00']);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('available_until'))->toBe(true);

        $data = $this->getValidUpdateData([
            'available_from' => '09:00:00',
            'available_until' => '17:00:00',
        ]);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_validates_blocked_days_in_update(): void
    {
        $data = $this->getValidUpdateData(['blocked_days' => ['InvalidDay']]);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->fails())->toBe(true);
        expect($validator->errors()->has('blocked_days.0'))->toBe(true);

        $data = $this->getValidUpdateData(['blocked_days' => ['Wednesday', 'Friday']]);
        $validator = $this->getUpdateValidator($data, $this->room);
        expect($validator->passes())->toBe(true);
    }

    /** @test */
    public function it_has_same_custom_attributes_and_messages_as_store(): void
    {
        $storeRequest = new StoreRoomRequest();
        $updateRequest = new UpdateRoomRequest();

        expect($updateRequest->attributes())->toBe($storeRequest->attributes());
        expect($updateRequest->messages())->toBe($storeRequest->messages());
    }

    private function getValidStoreData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Room',
            'code' => 'TR-001',
            'building' => 'Building A',
            'floor' => '1st',
            'type' => Room::TYPE_CLASSROOM,
            'capacity' => 50,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
            'requires_approval' => false,
            'available_from' => '08:00:00',
            'available_until' => '18:00:00',
            'blocked_days' => ['Sunday'],
            'description' => 'A test room',
            'usage_guidelines' => 'Please keep clean',
            'booking_notes' => 'Contact admin for booking',
        ], $overrides);
    }

    private function getValidUpdateData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Updated Room',
            'code' => 'UR-001',
            'building' => 'Building B',
            'floor' => '2nd',
            'type' => Room::TYPE_LABORATORY,
            'capacity' => 30,
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
            'requires_approval' => true,
        ], $overrides);
    }

    private function getUpdateValidator(array $data, Room $room): \Illuminate\Validation\Validator
    {
        $request = new UpdateRoomRequest();
        
        // Mock the route method to return the room
        $request->setRouteResolver(function () use ($room) {
            $route = \Mockery::mock();
            $route->shouldReceive('parameter')->with('room')->andReturn($room);
            return $route;
        });

        return Validator::make($data, $request->rules());
    }
}