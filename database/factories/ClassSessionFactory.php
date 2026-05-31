<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->time('H:i:s');
        $endTime = date('H:i:s', strtotime($startTime) + 7200); // Add 2 hours

        return [
            'course_offering_id' => CourseOffering::factory(),
            'lecture_id' => Lecture::factory(),
            'room_id' => Room::factory(),
            'session_title' => $this->faker->words(3, true),
            'session_description' => $this->faker->optional()->sentence(),
            'session_date' => $this->faker->date(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => 120,
            'session_type' => $this->faker->randomElement(['lecture', 'tutorial', 'laboratory', 'assessment']),
            'delivery_mode' => $this->faker->randomElement(['in_person', 'online', 'hybrid']),
            'status' => $this->faker->randomElement(['scheduled', 'in_progress', 'completed', 'cancelled']),
            'attendance_required' => $this->faker->boolean(80),
            'attendance_tracking_enabled' => $this->faker->boolean(90),
            'is_assessment' => $this->faker->boolean(20),
            'assessment_weight' => $this->faker->optional()->randomFloat(2, 0, 100),
            'assessment_duration_minutes' => $this->faker->optional()->numberBetween(60, 180),
            'is_recurring' => $this->faker->boolean(10),
            'sequence_number' => $this->faker->numberBetween(1, 20),
            'expected_attendees' => $this->faker->optional()->numberBetween(10, 50),
            'actual_attendees' => $this->faker->optional()->numberBetween(0, 50),
            'attendance_percentage' => $this->faker->optional()->randomFloat(2, 0, 100),
        ];
    }
}
