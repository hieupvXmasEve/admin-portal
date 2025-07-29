<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\Lecture;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseOffering>
 */
class CourseOfferingFactory extends Factory
{
    protected $model = CourseOffering::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            'curriculum_unit_id' => CurriculumUnit::factory(),
            'lecture_id' => Lecture::factory(),
            'section_code' => $this->faker->optional()->bothify('SEC-?##'),
            'max_capacity' => $this->faker->numberBetween(20, 100),
            'current_enrollment' => 0,
            'waitlist_capacity' => $this->faker->numberBetween(5, 20),
            'current_waitlist' => 0,
            'delivery_mode' => $this->faker->randomElement(['in_person', 'online', 'hybrid', 'blended']),
            'schedule_days' => $this->faker->randomElements(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 2),
            'schedule_time_start' => $this->faker->time('H:i:s'),
            'schedule_time_end' => $this->faker->time('H:i:s'),
            'location' => $this->faker->optional()->bothify('Room ###'),
            'is_active' => true,
            'enrollment_status' => $this->faker->randomElement(['open', 'closed', 'waitlist_only', 'cancelled']),
            'registration_start_date' => $this->faker->date(),
            'registration_end_date' => $this->faker->date(),
            'drop_deadline' => $this->faker->optional()->date(),
            'withdrawal_deadline' => $this->faker->optional()->date(),
            'special_requirements' => $this->faker->optional()->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}