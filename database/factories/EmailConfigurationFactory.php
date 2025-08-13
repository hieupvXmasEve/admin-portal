<?php

namespace Database\Factories;

use App\Models\EmailConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailConfiguration>
 */
class EmailConfigurationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailConfiguration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' SMTP',
            'host' => $this->faker->domainName,
            'port' => $this->faker->randomElement([25, 587, 465, 993, 995]),
            'username' => $this->faker->email,
            'password' => $this->faker->password,
            'encryption' => $this->faker->randomElement(['tls', 'ssl', 'none']),
            'from_address' => $this->faker->email,
            'from_name' => $this->faker->company,
            'is_active' => false,
            'daily_limit' => $this->faker->numberBetween(100, 5000),
            'rate_limit' => $this->faker->numberBetween(10, 500),
            'last_tested_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'test_result' => $this->faker->optional()->randomElement(['success', 'failed: Connection timeout', 'failed: Authentication failed']),
        ];
    }

    /**
     * Indicate that the configuration is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the configuration has been tested successfully.
     */
    public function tested(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_tested_at' => now(),
            'test_result' => 'success',
        ]);
    }

    /**
     * Indicate that the configuration test failed.
     */
    public function testFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_tested_at' => now(),
            'test_result' => 'failed: Connection timeout',
        ]);
    }
}
