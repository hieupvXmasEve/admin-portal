<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word . ' Permission ' . $this->faker->unique()->numberBetween(1000, 9999),
            'code' => strtoupper($this->faker->unique()->lexify('PERM??')),
            'description' => $this->faker->sentence,
        ];
    }
}
