<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'building_id' => Building::factory(),
            'name' => $this->faker->words(2, true),
            'code' => $this->faker->bothify('R-###'),
            'floor' => $this->faker->randomElement(['Ground', '1st', '2nd', '3rd', '4th', '5th']),
            'type' => $this->faker->randomElement(Room::getTypes()),
            'capacity' => $this->faker->numberBetween(10, 200),
            'status' => Room::STATUS_AVAILABLE,
            'is_bookable' => true,
            'requires_approval' => $this->faker->boolean(30),
            'available_from' => '08:00:00',
            'available_until' => '18:00:00',
            'blocked_days' => [],
            'description' => $this->faker->optional()->sentence(),
            'usage_guidelines' => $this->faker->optional()->paragraph(),
            'booking_notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the room is not bookable.
     */
    public function notBookable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bookable' => false,
        ]);
    }

    /**
     * Indicate that the room is out of service.
     */
    public function outOfService(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Room::STATUS_OUT_OF_SERVICE,
        ]);
    }

    public function forBuilding(Building $building): static
    {
        return $this->state(fn (array $attributes) => [
            'building_id' => $building->id,
            'campus_id' => $building->campus_id,
        ]);
    }
}
