<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventParticipant>
 */
class EventParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registeredAt = $this->faker->dateTimeBetween('-14 days', 'now');

        return [
            'event_id' => \App\Models\Event::factory(),
            'student_id' => \App\Models\Student::factory(),
            'status' => 'registered',
            'registered_at' => $registeredAt,
            'checkin_time' => null,
            'checkin_device_info' => null,
            'checkin_staff_id' => null,
            'gold_awarded' => false,
            'awarded_at' => null,
        ];
    }

    /**
     * Indicate that the participant is checked in.
     */
    public function checkedIn(): static
    {
        return $this->state(function (array $attributes) {
            $checkinTime = $this->faker->dateTimeBetween($attributes['registered_at'], 'now');

            return [
                'status' => 'checked_in',
                'checkin_time' => $checkinTime,
                'checkin_device_info' => [
                    'device_type' => $this->faker->randomElement(['mobile', 'tablet', 'desktop']),
                    'browser' => $this->faker->randomElement(['Chrome', 'Safari', 'Firefox', 'Edge']),
                    'ip_address' => $this->faker->ipv4(),
                    'user_agent' => $this->faker->userAgent(),
                ],
                'checkin_staff_id' => \App\Models\User::factory(),
            ];
        });
    }

    /**
     * Indicate that the participant has completed the event.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $checkinTime = $this->faker->dateTimeBetween($attributes['registered_at'], '-1 hour');
            $awardedAt = $this->faker->dateTimeBetween($checkinTime, 'now');

            return [
                'status' => 'completed',
                'checkin_time' => $checkinTime,
                'checkin_device_info' => [
                    'device_type' => $this->faker->randomElement(['mobile', 'tablet', 'desktop']),
                    'browser' => $this->faker->randomElement(['Chrome', 'Safari', 'Firefox', 'Edge']),
                    'ip_address' => $this->faker->ipv4(),
                    'user_agent' => $this->faker->userAgent(),
                ],
                'checkin_staff_id' => \App\Models\User::factory(),
                'gold_awarded' => true,
                'awarded_at' => $awardedAt,
            ];
        });
    }

    /**
     * Indicate that the participant cancelled their registration.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the participant has been awarded gold.
     */
    public function awarded(): static
    {
        return $this->state(function (array $attributes) {
            $awardedAt = $this->faker->dateTimeBetween($attributes['registered_at'], 'now');

            return [
                'gold_awarded' => true,
                'awarded_at' => $awardedAt,
            ];
        });
    }

    /**
     * Indicate that the participant checked in with specific device info.
     */
    public function withDeviceInfo(?array $deviceInfo = null): static
    {
        return $this->state(fn (array $attributes) => [
            'checkin_device_info' => $deviceInfo ?? [
                'device_type' => $this->faker->randomElement(['mobile', 'tablet', 'desktop']),
                'browser' => $this->faker->randomElement(['Chrome', 'Safari', 'Firefox', 'Edge']),
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'location' => $this->faker->optional()->city(),
            ],
        ]);
    }

    /**
     * Indicate that the participant was checked in by a specific staff member.
     */
    public function checkedInBy(\App\Models\User $staff): static
    {
        return $this->state(fn (array $attributes) => [
            'checkin_staff_id' => $staff->id,
        ]);
    }

    /**
     * Create a participant for a specific event and student.
     */
    public function forEventAndStudent(\App\Models\Event $event, \App\Models\Student $student): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
            'student_id' => $student->id,
        ]);
    }
}
