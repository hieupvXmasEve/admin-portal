<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Campus;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamResitSession>
 */
class ExamResitSessionFactory extends Factory
{
    protected $model = ExamResitSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_room_slot_id' => ExamRoomSlot::factory(),
            'unit_id' => Unit::factory(),
            'semester_id' => Semester::factory(),
            'campus_id' => Campus::factory(),
            'syllabus_template_id' => null,
            'status' => ExamResitSession::STATUS_SCHEDULED,
            'expected_candidates' => 0,
            'instructions' => null,
            'materials_allowed' => null,
        ];
    }
}
