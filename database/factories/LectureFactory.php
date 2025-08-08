<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Lecture;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lecture>
 */
class LectureFactory extends Factory
{
    protected $model = Lecture::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => $this->faker->unique()->bothify('EMP####'),
            'campus_id' => Campus::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'academic_rank' => $this->faker->randomElement(['lecturer', 'senior_lecturer', 'associate_professor', 'professor']),
            'department' => $this->faker->randomElement(['Computer Science', 'Engineering', 'Business', 'Arts', 'Science']),
            'specialization' => $this->faker->randomElement(['Software Development', 'Data Science', 'Network Security', 'Web Development']),
            'hire_date' => $this->faker->date('Y-m-d', '2020-01-01'),
            'employment_type' => $this->faker->randomElement(['full_time', 'part_time', 'contract', 'visiting']),
            'employment_status' => $this->faker->randomElement(['active', 'on_leave', 'sabbatical']),
            'office_address' => $this->faker->optional()->bothify('Building ##, Room ###'),
            'biography' => $this->faker->optional()->paragraph(),
            'salary' => $this->faker->numberBetween(50000, 150000),
            'is_active' => true,
            'is_available_for_assignment' => true,
        ];
    }
}
