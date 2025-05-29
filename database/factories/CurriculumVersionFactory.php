<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CurriculumVersion>
 */
class CurriculumVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CurriculumVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'version_code' => 'V' . fake()->randomFloat(1, 1.0, 9.9),
            'effective_from_semester_id' => Semester::inRandomOrder()->first()?->id,
        ];
    }
}
