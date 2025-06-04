<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Semester::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+6 months');
        $endDate = Carbon::instance($startDate)->addMonths(4);
        $enrollmentStart = Carbon::instance($startDate)->subMonths(2);
        $enrollmentEnd = Carbon::instance($startDate)->subWeeks(1);

        return [
            'code' => $this->faker->unique()->regexify('[A-Z]{3}[0-9]{4}'),
            'name' => $this->faker->randomElement([
                'Spring 2024',
                'Fall 2024',
                'Summer 2024',
                'Spring 2025',
                'Fall 2025',
                'Summer 2025'
            ]),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'enrollment_start_date' => $enrollmentStart,
            'enrollment_end_date' => $enrollmentEnd,
            'is_active' => $this->faker->boolean(20), // 20% chance of being active
            'is_archived' => $this->faker->boolean(10), // 10% chance of being archived
        ];
    }

    /**
     * Create an active semester.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    /**
     * Create an archived semester.
     */
    public function archived(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
            'is_archived' => true,
        ]);
    }

    /**
     * Create a spring semester.
     */
    public function spring(): static
    {
        return $this->state(function (array $attributes) {
            $year = $this->faker->numberBetween(2024, 2026);
            $startDate = Carbon::create($year, 1, 15);
            $endDate = $startDate->copy()->addMonths(4);

            return [
                'code' => "SPR{$year}",
                'name' => "Spring {$year}",
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }

    /**
     * Create a fall semester.
     */
    public function fall(): static
    {
        return $this->state(function (array $attributes) {
            $year = $this->faker->numberBetween(2024, 2026);
            $startDate = Carbon::create($year, 8, 15);
            $endDate = $startDate->copy()->addMonths(4);

            return [
                'code' => "FALL{$year}",
                'name' => "Fall {$year}",
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        });
    }
}
