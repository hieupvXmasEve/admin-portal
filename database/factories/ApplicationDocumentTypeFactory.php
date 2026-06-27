<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApplicationDocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocumentType>
 */
class ApplicationDocumentTypeFactory extends Factory
{
    protected $model = ApplicationDocumentType::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('doc_???_##'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['identity', 'transcript', 'certificate', 'other']),
            'required' => false,
            'int_required' => false,
            'active' => true,
            'order' => fake()->numberBetween(0, 50),
            'step' => null,
        ];
    }

    /**
     * Required for every applicant.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => true,
        ]);
    }

    /**
     * Required only for international applicants.
     */
    public function internationalRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'required' => false,
            'int_required' => true,
        ]);
    }

    /**
     * Inactive catalog entry (retired in the CRM; should not be surfaced).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
