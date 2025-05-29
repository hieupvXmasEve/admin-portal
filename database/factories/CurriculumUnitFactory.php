<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CurriculumUnit>
 */
class CurriculumUnitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CurriculumUnit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'curriculum_version_id' => CurriculumVersion::factory(),
            'unit_id' => Unit::factory(),
            'group_type' => fake()->randomElement(['core', 'major', 'elective', 'minor', 'second_major']),
        ];
    }
}
