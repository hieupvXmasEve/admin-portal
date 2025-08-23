<?php

namespace Database\Factories;

use App\Models\UploadRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UploadRecord>
 */
class UploadRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = UploadRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contexts = ['avatar', 'assignment', 'general', 'document'];
        $mimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ];

        $context = $this->faker->randomElement($contexts);
        $mimeType = $this->faker->randomElement($mimeTypes);
        $extension = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'jpg'
        };

        $filename = $this->faker->uuid() . '.' . $extension;
        $originalName = $this->faker->words(2, true) . '.' . $extension;
        $path = $context . '/' . date('Y/m/d') . '/' . $filename;

        return [
            'filename' => $filename,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
            'context' => $context,
            'path' => $path,
            'disk' => 'public',
            'url' => '/storage/' . $path,
            'hash' => hash('sha256', $this->faker->text()),
            'user_id' => User::factory(),
            'metadata' => [
                'width' => $this->faker->numberBetween(100, 2000),
                'height' => $this->faker->numberBetween(100, 2000),
                'alt_text' => $this->faker->sentence(),
            ],
            'expires_at' => null,
        ];
    }

    /**
     * Create an avatar upload record.
     */
    public function avatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'context' => 'avatar',
            'path' => 'avatars/' . date('Y/m/d') . '/' . $attributes['filename'],
            'url' => '/storage/avatars/' . date('Y/m/d') . '/' . $attributes['filename'],
            'metadata' => [
                'width' => $this->faker->numberBetween(100, 500),
                'height' => $this->faker->numberBetween(100, 500),
                'is_profile_image' => true,
            ],
        ]);
    }

    /**
     * Create an assignment upload record.
     */
    public function assignment(): static
    {
        return $this->state(fn (array $attributes) => [
            'context' => 'assignment',
            'path' => 'assignments/' . date('Y/m/d') . '/' . $attributes['filename'],
            'url' => '/storage/assignments/' . date('Y/m/d') . '/' . $attributes['filename'],
            'metadata' => [
                'width' => $this->faker->numberBetween(500, 2000),
                'height' => $this->faker->numberBetween(500, 2000),
                'assignment_id' => $this->faker->numberBetween(1, 100),
            ],
        ]);
    }

    /**
     * Create a temporary upload record.
     */
    public function temporary(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addHours(24),
            'context' => 'temporary',
            'path' => 'temp/' . date('Y/m/d') . '/' . $attributes['filename'],
            'url' => '/storage/temp/' . date('Y/m/d') . '/' . $attributes['filename'],
        ]);
    }

    /**
     * Create an expired upload record.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHours(1),
            'context' => 'temporary',
        ]);
    }

    /**
     * Create a large file upload record.
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => $this->faker->numberBetween(10485760, 52428800), // 10MB to 50MB
            'metadata' => [
                'width' => $this->faker->numberBetween(2000, 5000),
                'height' => $this->faker->numberBetween(2000, 5000),
                'is_large_file' => true,
            ],
        ]);
    }

    /**
     * Create an upload record without a user.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
