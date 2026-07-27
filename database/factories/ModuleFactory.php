<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Module::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'code' => strtoupper(fake()->unique()->bothify('MOD-###')),
            'name' => fake()->sentence(3, true),
            'description' => fake()->optional()->sentence(),
            'grading_type' => fake()->randomElement(['grade', 'pass_fail']),
            'total_credits' => 0,
            'prerequisite_module_id' => null,
        ];
    }
}
