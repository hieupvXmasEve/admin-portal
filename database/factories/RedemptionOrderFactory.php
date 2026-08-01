<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\RedemptionOrder;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedemptionOrder>
 */
class RedemptionOrderFactory extends Factory
{
    protected $model = RedemptionOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'campus_id' => Campus::factory(),
            'code' => 'MRD-'.now()->format('Ym').'-'.strtoupper(fake()->unique()->bothify('######')),
            'status' => RedemptionOrder::STATUS_PENDING_REVIEW,
            'previous_status' => null,
            'method' => RedemptionOrder::METHOD_PICKUP,
            'total_gold' => fake()->numberBetween(10, 500),
            'idempotency_key' => null,
        ];
    }
}
