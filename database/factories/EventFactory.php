<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $endTime = $this->faker->dateTimeBetween($startTime, $startTime->format('Y-m-d H:i:s') . ' +4 hours');

        return [
            'campus_id' => \App\Models\Campus::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(3),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'location' => $this->faker->address(),
            'gold_reward_amount' => $this->faker->randomFloat(2, 10, 100),
            'max_participants' => $this->faker->optional(0.7)->numberBetween(20, 200),
            'qr_code' => $this->faker->unique()->uuid(),
            'organizer_type' => 'school',
            'organizer_id' => function (array $attributes) {
                return $attributes['campus_id'];
            },
            'status' => $this->faker->randomElement(['draft', 'published']),
            'published_at' => function (array $attributes) {
                return $attributes['status'] === 'published' ? $this->faker->dateTimeBetween('-7 days', 'now') : null;
            },
            'created_by_user_id' => \App\Models\User::factory(),
            'is_manual' => false,
            'is_historical' => false,
            'created_by_admin_id' => null,
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Indicate that the event is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the event is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => $this->faker->dateTimeBetween('-3 days', 'now'),
        ]);
    }

    /**
     * Indicate that the event is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'start_time' => $this->faker->dateTimeBetween('-7 days', '-2 days'),
            'end_time' => $this->faker->dateTimeBetween('-2 days', '-1 day'),
            'completed_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    /**
     * Indicate that the event is ongoing.
     */
    public function ongoing(): static
    {
        $startTime = $this->faker->dateTimeBetween('-2 hours', 'now');

        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'start_time' => $startTime,
            'end_time' => $this->faker->dateTimeBetween('now', '+2 hours'),
            'published_at' => $this->faker->dateTimeBetween('-7 days', $startTime),
        ]);
    }

    /**
     * Indicate that the event has limited capacity.
     */
    public function withCapacity(?int $capacity = null): static
    {
        return $this->state(fn (array $attributes) => [
            'max_participants' => $capacity ?? $this->faker->numberBetween(20, 100),
        ]);
    }

    /**
     * Indicate that the event has unlimited capacity.
     */
    public function unlimitedCapacity(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_participants' => null,
        ]);
    }

    /**
     * Indicate that the event has high gold rewards.
     */
    public function highReward(): static
    {
        return $this->state(fn (array $attributes) => [
            'gold_reward_amount' => $this->faker->randomFloat(2, 100, 500),
        ]);
    }

    /**
     * Indicate that the event is in the future.
     */
    public function future(): static
    {
        $startTime = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $endTime = $this->faker->dateTimeBetween($startTime, $startTime->format('Y-m-d H:i:s') . ' +4 hours');

        return $this->state(fn (array $attributes) => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Indicate that the event is in the past.
     */
    public function past(): static
    {
        $startTime = $this->faker->dateTimeBetween('-7 days', '-2 days');
        $endTime = $this->faker->dateTimeBetween($startTime, $startTime->format('Y-m-d H:i:s') . ' +4 hours');

        return $this->state(fn (array $attributes) => [
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Indicate that the event is manually created.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_manual' => true,
            'created_by_admin_id' => \App\Models\User::factory(),
            'status' => 'completed',
            'completed_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    /**
     * Indicate that the event is historical.
     */
    public function historical(): static
    {
        $startTime = $this->faker->dateTimeBetween('-1 year', '-1 month');
        $endTime = $this->faker->dateTimeBetween($startTime, $startTime->format('Y-m-d H:i:s') . ' +4 hours');

        return $this->state(fn (array $attributes) => [
            'is_historical' => true,
            'is_manual' => true,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => 'completed',
            'published_at' => $startTime,
            'completed_at' => $endTime,
            'created_by_admin_id' => \App\Models\User::factory(),
        ]);
    }

    /**
     * Indicate that the event is both manual and historical.
     */
    public function manualHistorical(): static
    {
        return $this->manual()->historical();
    }
}
