<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Program;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * How to use
 * - Create a template: `SyllabusTemplate::factory()->create();`
 * - Default for a unit: `SyllabusTemplate::factory()->defaultForUnit()->create();`
 * - Clone from another: `SyllabusTemplate::factory()->clonedFrom($existing)->create();`
 */

/**
 * @extends Factory<SyllabusTemplate>
 */
class SyllabusTemplateFactory extends Factory
{
    protected $model = SyllabusTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $version = $this->faker->randomElement(['1.0', '1.1', '2.0', '2.1', '3.0']);

        return [
            'unit_id' => Unit::factory(),
            'title' => $this->faker->sentence(4),
            'version' => $version,
            'description' => $this->faker->paragraphs(2, true),

            'total_hours' => $this->faker->numberBetween(30, 180),
            'total_sessions' => $this->faker->numberBetween(10, 60),
            'min_attendance_threshold' => 80.00,
            'min_grade_threshold' => 60.00,
            'exam_resit_max_attempts' => 1,
            'exam_resit_registration_window_days' => null,
            'exam_resit_late_payment_grace_days' => 14,
            'exam_resit_allow_unpaid_sitting' => false,
            'learning_outcomes' => $this->faker->sentences(3),
            'grading_criteria' => [
                ['name' => 'Assignments', 'weight' => 40],
                ['name' => 'Midterm', 'weight' => 20],
                ['name' => 'Final Exam', 'weight' => 40],
            ],
            'required_materials' => [
                'Textbook: '.$this->faker->sentence(3),
                'Laptop',
                'Software: '.$this->faker->randomElement(['RStudio', 'VS Code', 'Matlab']),
            ],
            'assessment_policy' => $this->faker->paragraph(),

            'applicable_program_id' => fn () => $this->faker->boolean(60) ? Program::factory() : null,
            'applicable_campus_id' => fn () => $this->faker->boolean(60) ? Campus::factory() : null,
            'delivery_mode' => fn () => $this->faker->boolean(80)
                ? $this->faker->randomElement(['in_person', 'online', 'hybrid', 'blended'])
                : null,

            'is_default' => $this->faker->boolean(20),
            'is_active' => $this->faker->boolean(90),
            'source_template_id' => null,

            'created_by' => User::factory(),
        ];
    }

    /**
     * Mark as default template for unit.
     */
    public function defaultForUnit(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Mark as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Clone from an existing template.
     */
    public function clonedFrom(SyllabusTemplate $template): static
    {
        return $this->state(fn (array $attributes) => [
            'source_template_id' => $template->getKey(),
        ]);
    }
}
