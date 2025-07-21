<?php

namespace Database\Factories;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentComponentDetail>
 */
class AssessmentComponentDetailFactory extends Factory
{
    protected $model = AssessmentComponentDetail::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $names = [
            'Proposal',
            'Literature Review',
            'Methodology',
            'Implementation',
            'Testing',
            'Documentation',
            'Presentation',
            'Report',
            'Prototype',
            'Analysis',
            'Design',
            'Evaluation',
            'Research',
            'Development',
            'Demo',
            'Reflection'
        ];

        return [
            'assessment_component_id' => AssessmentComponent::factory(),
            'name' => $this->faker->randomElement($names),
            'weight' => $this->faker->numberBetween(5, 20),
        ];
    }

    /**
     * Indicate that this is a proposal detail.
     */
    public function proposal(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Proposal Submission',
            'weight' => $this->faker->numberBetween(5, 15),
        ]);
    }

    /**
     * Indicate that this is a presentation detail.
     */
    public function presentation(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Final Presentation',
            'weight' => $this->faker->numberBetween(10, 20),
        ]);
    }

    /**
     * Indicate that this is a report detail.
     */
    public function report(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'Written Report',
            'weight' => $this->faker->numberBetween(15, 25),
        ]);
    }
}
