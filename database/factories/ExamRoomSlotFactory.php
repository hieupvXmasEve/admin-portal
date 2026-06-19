<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\ExamRoomSlot;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamRoomSlot>
 */
class ExamRoomSlotFactory extends Factory
{
    protected $model = ExamRoomSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campus_id' => Campus::factory(),
            'room_id' => Room::factory(),
            'room_booking_id' => null,
            'exam_date' => '2026-07-01',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'capacity' => 40,
            'status' => ExamRoomSlot::STATUS_SCHEDULED,
            'notes' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExamRoomSlot::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }
}
