<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Campus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Building>
 */
class BuildingFactory extends Factory
{
    protected $model = Building::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Building types and prefixes
        $buildingTypes = [
            'Academic' => ['A', 'ACAD', 'EDU'],
            'Science' => ['S', 'SCI', 'LAB'],
            'Engineering' => ['E', 'ENG', 'TECH'],
            'Business' => ['B', 'BIZ', 'COM'],
            'Library' => ['L', 'LIB', 'INFO'],
            'Administration' => ['ADM', 'OFFICE', 'MAIN'],
            'Sports' => ['GYM', 'SPORT', 'REC'],
            'Student' => ['STU', 'UNION', 'HUB'],
        ];

        $typeKey = $this->faker->randomKey($buildingTypes);
        $prefixes = $buildingTypes[$typeKey];
        $prefix = $this->faker->randomElement($prefixes);

        // Generate unique building code
        $number = $this->faker->numberBetween(1, 99);
        $code = $prefix.str_pad($number, 2, '0', STR_PAD_LEFT);

        return [
            'campus_id' => Campus::factory(),
            'name' => $this->generateBuildingName($typeKey),
            'code' => $code,
            'description' => $this->generateDescription($typeKey),
            'address' => $this->faker->streetAddress(),
        ];
    }

    /**
     * Generate realistic building names based on type
     */
    private function generateBuildingName(string $type): string
    {
        $names = [
            'Academic' => [
                'Academic Building {number}',
                'Classroom Complex {letter}',
                'Education Center',
                'Learning Hub',
                'Teaching Block {number}',
                'Academic Hall',
            ],
            'Science' => [
                'Science Laboratory Building',
                'Research Complex {letter}',
                'Innovation Center',
                'STEM Building',
                'Science Block {number}',
                'Laboratory Wing {letter}',
            ],
            'Engineering' => [
                'Engineering Building',
                'Technology Center',
                'Innovation Lab',
                'Engineering Complex {letter}',
                'Tech Hub',
                'Manufacturing Building',
            ],
            'Business' => [
                'Business School Building',
                'Commerce Center',
                'Management Building',
                'Corporate Training Center',
                'Business Hub',
                'Executive Education Building',
            ],
            'Library' => [
                'Central Library',
                'Information Commons',
                'Learning Resource Center',
                'Digital Library',
                'Study Commons',
                'Knowledge Hub',
            ],
            'Administration' => [
                'Administration Building',
                'Main Office',
                'Executive Building',
                'Campus Services Center',
                'Student Services Building',
                'Administrative Complex',
            ],
            'Sports' => [
                'Sports Complex',
                'Gymnasium',
                'Recreation Center',
                'Fitness Center',
                'Athletic Building',
                'Wellness Center',
            ],
            'Student' => [
                'Student Union Building',
                'Student Center',
                'Campus Life Building',
                'Student Hub',
                'Community Center',
                'Student Services Building',
            ],
        ];

        $templates = $names[$type] ?? ['Building {number}'];
        $template = $this->faker->randomElement($templates);

        // Replace placeholders
        $template = str_replace('{number}', $this->faker->numberBetween(1, 9), $template);
        $template = str_replace('{letter}', chr(65 + $this->faker->numberBetween(0, 7)), $template); // A-H

        return $template;
    }

    /**
     * Generate realistic descriptions based on building type
     */
    private function generateDescription(string $type): string
    {
        $descriptions = [
            'Academic' => [
                'Multi-purpose academic building housing various classrooms and lecture halls.',
                'Modern teaching facility equipped with state-of-the-art audio-visual equipment.',
                'General purpose academic building for undergraduate and graduate courses.',
                'Flexible learning spaces designed for interactive and collaborative learning.',
            ],
            'Science' => [
                'Advanced research facility with specialized laboratories and equipment.',
                'Dedicated science building with chemistry, physics, and biology labs.',
                'Modern research complex supporting undergraduate and graduate research.',
                'State-of-the-art laboratory facility for scientific research and education.',
            ],
            'Engineering' => [
                'Engineering facility with workshops, labs, and project spaces.',
                'Technology center featuring CAD labs and engineering workshops.',
                'Modern engineering building with design studios and fabrication labs.',
                'Comprehensive facility for engineering education and research.',
            ],
            'Business' => [
                'Business education facility with case study rooms and presentation spaces.',
                'Modern business school building with executive meeting rooms.',
                'Corporate-style learning environment for business education.',
                'Professional development center for business and management programs.',
            ],
            'Library' => [
                'Comprehensive library facility with study areas and research collections.',
                'Modern information center with digital resources and collaborative spaces.',
                'Multi-level library with quiet study areas and group work spaces.',
                'Academic library supporting research and learning across all disciplines.',
            ],
            'Administration' => [
                'Central administrative facility housing various campus offices.',
                'Main administration building for student and academic services.',
                'Administrative complex providing comprehensive campus support services.',
                'Primary administrative facility for university operations.',
            ],
            'Sports' => [
                'Comprehensive sports facility with courts, fitness equipment, and training areas.',
                'Modern recreation center supporting student wellness and athletic programs.',
                'Multi-sport facility providing opportunities for recreational and competitive activities.',
                'State-of-the-art athletic facility for sports programs and fitness activities.',
            ],
            'Student' => [
                'Central hub for student activities, organizations, and support services.',
                'Student-centered facility providing social and recreational spaces.',
                'Community building fostering student engagement and campus life.',
                'Multi-purpose student facility supporting various campus activities.',
            ],
        ];

        $options = $descriptions[$type] ?? ['General purpose building for campus activities.'];

        return $this->faker->randomElement($options);
    }

    /**
     * State modifier for specific campus
     */
    public function forCampus(Campus $campus): static
    {
        return $this->state(fn (array $attributes) => [
            'campus_id' => $campus->id,
        ]);
    }

    /**
     * State modifier for academic buildings
     */
    public function academic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->generateBuildingName('Academic'),
            'code' => 'A'.str_pad($this->faker->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
            'description' => $this->generateDescription('Academic'),
        ]);
    }

    /**
     * State modifier for science buildings
     */
    public function science(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->generateBuildingName('Science'),
            'code' => 'S'.str_pad($this->faker->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
            'description' => $this->generateDescription('Science'),
        ]);
    }
}
