<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DepartmentMembership>
 */
class DepartmentMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'user_id' => User::factory(),
            'department_role' => fake()->randomElement(['head', 'staff']),
            'is_active' => true,
        ];
    }
}
