<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EgcBlock>
 */
class EgcBlockFactory extends Factory
{
    protected $model = EgcBlock::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'semester_id' => Semester::factory(),
            'block_number' => 1,
            'level_number' => 1,
            'result' => EgcBlock::RESULT_PENDING,
            'attendance_rate' => null,
            'is_retake' => false,
            'finance_charge_id' => null,
            'retake_discount_id' => null,
            'synced_at' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => EgcBlock::RESULT_FAIL,
            'attendance_rate' => 85.0,
        ]);
    }

    public function passed(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => EgcBlock::RESULT_PASS,
            'attendance_rate' => 90.0,
        ]);
    }

    public function retake(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_retake' => true,
        ]);
    }
}
