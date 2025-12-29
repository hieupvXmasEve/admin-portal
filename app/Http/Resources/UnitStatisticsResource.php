<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalStudents = (int) ($this->total_students ?? 0);
        $studentsPassed = (int) ($this->students_passed ?? 0);

        return [
            'id' => $this->id,
            'unit_code' => $this->unit_code,
            'unit_name' => $this->unit_name,
            'credit_hours' => (int) ($this->credit_hours ?? 0),
            'total_students' => $totalStudents,
            'offerings_count' => (int) ($this->offerings_count ?? 0),
            'average_attendance' => round((float) ($this->average_attendance ?? 0), 2),
            'average_grade' => round((float) ($this->average_grade ?? 0), 2),
            'grade_distribution' => [
                'A+' => (int) ($this->grade_a_plus ?? 0),
                'A' => (int) ($this->grade_a ?? 0),
                'B+' => (int) ($this->grade_b_plus ?? 0),
                'B' => (int) ($this->grade_b ?? 0),
                'C+' => (int) ($this->grade_c_plus ?? 0),
                'C' => (int) ($this->grade_c ?? 0),
                'D+' => (int) ($this->grade_d_plus ?? 0),
                'D' => (int) ($this->grade_d ?? 0),
                'F' => (int) ($this->grade_f ?? 0),
            ],
            'pass_rate' => $totalStudents > 0
                ? round(($studentsPassed / $totalStudents) * 100, 2)
                : 0.0,
        ];
    }
}
