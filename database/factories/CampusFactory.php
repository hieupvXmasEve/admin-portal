<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Modules\Finance\Dng\Models\DngCampusMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campus>
 */
class CampusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'name' => fake()->name().' Campus',
            'code' => $code,
            'address' => fake()->address(),
        ];
    }

    public function withDngMapping(?string $providerCode = null): static
    {
        return $this->afterCreating(function (Campus $campus) use ($providerCode): void {
            DngCampusMapping::query()->create([
                'campus_id' => $campus->id,
                'provider_code' => $providerCode ?? $campus->code,
            ]);
        });
    }
}
