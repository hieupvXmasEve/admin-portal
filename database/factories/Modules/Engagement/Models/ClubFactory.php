<?php

namespace Database\Factories\Modules\Engagement\Models;

use App\Models\Campus;
use App\Modules\Engagement\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    protected $model = Club::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clubTypes = [
            'Academic', 'Sports', 'Cultural', 'Technology', 'Arts', 'Music',
            'Drama', 'Science', 'Literature', 'Photography', 'Gaming', 'Volunteer',
        ];

        $clubNames = [
            'Debate Society', 'Chess Club', 'Basketball Team', 'Photography Club',
            'Drama Society', 'Computer Science Club', 'Environmental Club', 'Music Band',
            'Art Society', 'Literature Club', 'Robotics Club', 'Volunteer Corps',
        ];

        $socialPlatforms = ['facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'discord'];
        $socialLinks = [];

        // Randomly add 1-3 social media links
        $numLinks = fake()->numberBetween(1, 3);
        $selectedPlatforms = fake()->randomElements($socialPlatforms, $numLinks);

        foreach ($selectedPlatforms as $platform) {
            $socialLinks[$platform] = fake()->url();
        }

        $achievements = [];
        $numAchievements = fake()->numberBetween(0, 5);

        for ($i = 0; $i < $numAchievements; $i++) {
            $achievements[] = [
                'title' => fake()->sentence(3),
                'description' => fake()->sentence(8),
                'date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                'category' => fake()->randomElement(['competition', 'community_service', 'academic', 'cultural']),
            ];
        }

        return [
            'campus_id' => Campus::factory(),
            'name' => fake()->randomElement($clubNames),
            'description' => fake()->paragraphs(2, true),
            'founded_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'avatar_url' => fake()->optional(0.7)->imageUrl(200, 200, 'people'),
            'thumbnail_url' => fake()->optional(0.6)->imageUrl(100, 100, 'people'),
            'cover_url' => fake()->optional(0.8)->imageUrl(800, 300, 'business'),
            'social_links' => $socialLinks,
            'contact_email' => fake()->optional(0.8)->safeEmail(),
            'contact_phone' => fake()->optional(0.6)->phoneNumber(),
            'status' => fake()->randomElement(['active', 'inactive']),
            'achievements' => $achievements,
        ];
    }

    /**
     * Indicate that the club is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the club is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Create a club with specific type-based name and description.
     */
    public function ofType(string $type): static
    {
        $typeData = [
            'academic' => [
                'names' => ['Debate Society', 'Academic Excellence Club', 'Honor Society', 'Research Club'],
                'descriptions' => [
                    'A club dedicated to academic excellence and intellectual discourse among students.',
                    'Fostering academic achievement and scholarly activities within the campus community.',
                ],
            ],
            'sports' => [
                'names' => ['Basketball Team', 'Soccer Club', 'Tennis Club', 'Athletics Club'],
                'descriptions' => [
                    'Promoting physical fitness and competitive sports among students.',
                    'Building teamwork and sportsmanship through various athletic activities.',
                ],
            ],
            'technology' => [
                'names' => ['Computer Science Club', 'Robotics Club', 'Tech Innovation Society', 'Coding Club'],
                'descriptions' => [
                    'Exploring the latest in technology and computer science innovations.',
                    'Bringing together tech enthusiasts to learn, build, and innovate.',
                ],
            ],
            'cultural' => [
                'names' => ['Cultural Society', 'International Club', 'Heritage Club', 'Multicultural Association'],
                'descriptions' => [
                    'Celebrating diversity and promoting cultural understanding among students.',
                    'Organizing cultural events and fostering cross-cultural friendships.',
                ],
            ],
        ];

        $data = $typeData[$type] ?? $typeData['academic'];

        return $this->state(fn (array $attributes) => [
            'name' => fake()->randomElement($data['names']),
            'description' => fake()->randomElement($data['descriptions']).' '.fake()->sentence(),
        ]);
    }

    /**
     * Create a club with many achievements.
     */
    public function withManyAchievements(): static
    {
        $achievements = [];
        $numAchievements = fake()->numberBetween(5, 10);

        for ($i = 0; $i < $numAchievements; $i++) {
            $achievements[] = [
                'title' => fake()->sentence(3),
                'description' => fake()->sentence(8),
                'date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                'category' => fake()->randomElement(['competition', 'community_service', 'academic', 'cultural']),
            ];
        }

        return $this->state(fn (array $attributes) => [
            'achievements' => $achievements,
        ]);
    }

    /**
     * Create a club with comprehensive social media presence.
     */
    public function withFullSocialMedia(): static
    {
        return $this->state(fn (array $attributes) => [
            'social_links' => [
                'facebook' => 'https://facebook.com/'.fake()->userName(),
                'instagram' => 'https://instagram.com/'.fake()->userName(),
                'twitter' => 'https://twitter.com/'.fake()->userName(),
                'linkedin' => 'https://linkedin.com/company/'.fake()->slug(),
                'youtube' => 'https://youtube.com/channel/'.fake()->uuid(),
                'discord' => 'https://discord.gg/'.fake()->lexify('???????'),
            ],
        ]);
    }

    /**
     * Create a recently founded club.
     */
    public function recentlyFounded(): static
    {
        return $this->state(fn (array $attributes) => [
            'founded_date' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Create an established club.
     */
    public function established(): static
    {
        return $this->state(fn (array $attributes) => [
            'founded_date' => fake()->dateTimeBetween('-5 years', '-2 years'),
        ]);
    }
}
