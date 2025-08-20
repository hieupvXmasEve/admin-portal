<?php

namespace Database\Factories;

use App\Models\AssessmentComponent;
use App\Models\SyllabusTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentComponent>
 */
class AssessmentComponentFactory extends Factory
{
    protected $model = AssessmentComponent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['quiz', 'assignment', 'project', 'exam', 'online_activity', 'other'];
        $names = [
            'quiz' => ['Mid-term Quiz', 'Weekly Quiz', 'Pop Quiz'],
            'assignment' => ['Assignment 1', 'Assignment 2', 'Individual Assignment'],
            'project' => ['Group Project', 'Final Project', 'Capstone Project'],
            'exam' => ['Final Exam', 'Mid-term Exam', 'Practical Exam'],
            'online_activity' => ['Discussion Forum', 'Online Lab', 'Interactive Session'],
            'other' => ['Presentation', 'Portfolio', 'Case Study'],
        ];

        $type = $this->faker->randomElement($types);
        $name = $this->faker->randomElement($names[$type]);

        return [
            'syllabus_template_id' => SyllabusTemplate::factory(),
            'name' => $name,
            'weight' => $this->faker->numberBetween(10, 50),
            'type' => $type,
            'is_required_to_sit_final_exam' => $this->faker->boolean(80), // 80% chance of being required
        ];
    }

    /**
     * Indicate that the component is an exam.
     */
    public function exam(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'exam',
            'name' => 'Final Exam',
            'weight' => $this->faker->numberBetween(40, 60),
            'is_required_to_sit_final_exam' => true,
        ]);
    }

    /**
     * Indicate that the component is a quiz.
     */
    public function quiz(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'quiz',
            'name' => $this->faker->randomElement(['Mid-term Quiz', 'Weekly Quiz', 'Pop Quiz']),
            'weight' => $this->faker->numberBetween(10, 25),
            'is_required_to_sit_final_exam' => false,
        ]);
    }

    /**
     * Indicate that the component is a project.
     */
    public function project(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'project',
            'name' => $this->faker->randomElement(['Group Project', 'Final Project', 'Individual Project']),
            'weight' => $this->faker->numberBetween(20, 40),
            'is_required_to_sit_final_exam' => $this->faker->boolean(60),
        ]);
    }
}
