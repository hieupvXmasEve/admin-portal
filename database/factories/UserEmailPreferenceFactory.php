<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserEmailPreference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserEmailPreference>
 */
class UserEmailPreferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notification_type' => fake()->randomElement(array_keys(UserEmailPreference::getNotificationTypes())),
            'is_enabled' => fake()->boolean(80), // 80% chance of being enabled
            'frequency' => fake()->randomElement(array_keys(UserEmailPreference::getFrequencies())),
            'last_sent_at' => fake()->optional(0.3)->dateTimeBetween('-1 month', 'now'),
            'settings' => fake()->optional(0.2)->randomElements([
                'custom_setting' => fake()->word(),
                'priority' => fake()->randomElement(['high', 'medium', 'low']),
                'digest_time' => fake()->time('H:i'),
            ], fake()->numberBetween(1, 3), false),
        ];
    }

    /**
     * Indicate that the preference should be enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => true,
        ]);
    }

    /**
     * Indicate that the preference should be disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }

    /**
     * Indicate that the preference should be immediate.
     */
    public function immediate(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => UserEmailPreference::FREQUENCY_IMMEDIATE,
        ]);
    }

    /**
     * Indicate that the preference should be daily.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => UserEmailPreference::FREQUENCY_DAILY,
        ]);
    }

    /**
     * Indicate that the preference should be weekly.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => UserEmailPreference::FREQUENCY_WEEKLY,
        ]);
    }

    /**
     * Indicate that the preference should be never.
     */
    public function never(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => UserEmailPreference::FREQUENCY_NEVER,
            'is_enabled' => false,
        ]);
    }

    /**
     * Indicate that the preference is for welcome emails.
     */
    public function welcome(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_WELCOME,
        ]);
    }

    /**
     * Indicate that the preference is for grade notifications.
     */
    public function gradeNotification(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_GRADE_NOTIFICATION,
        ]);
    }

    /**
     * Indicate that the preference is for course registration.
     */
    public function courseRegistration(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_COURSE_REGISTRATION,
        ]);
    }

    /**
     * Indicate that the preference is for academic holds.
     */
    public function academicHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_ACADEMIC_HOLD,
        ]);
    }

    /**
     * Indicate that the preference is for enrollment confirmations.
     */
    public function enrollmentConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_ENROLLMENT_CONFIRMATION,
        ]);
    }

    /**
     * Indicate that the preference is for assessment deadlines.
     */
    public function assessmentDeadline(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_ASSESSMENT_DEADLINE,
        ]);
    }

    /**
     * Indicate that the preference is for system announcements.
     */
    public function systemAnnouncement(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_SYSTEM_ANNOUNCEMENT,
        ]);
    }

    /**
     * Indicate that the preference is for reminders.
     */
    public function reminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => UserEmailPreference::TYPE_REMINDER,
        ]);
    }

    /**
     * Indicate that the notification was recently sent.
     */
    public function recentlySent(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_sent_at' => now()->subHours(fake()->numberBetween(1, 12)),
        ]);
    }

    /**
     * Indicate that the notification was sent long ago.
     */
    public function sentLongAgo(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_sent_at' => now()->subDays(fake()->numberBetween(8, 30)),
        ]);
    }
}
