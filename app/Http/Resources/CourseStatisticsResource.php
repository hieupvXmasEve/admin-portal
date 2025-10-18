<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalStudents = (int) ($this->total_students ?? 0);
        $studentsAbsentExceeded = (int) ($this->students_absent_exceeded ?? 0);
        $studentsPassed = (int) ($this->students_passed ?? 0);

        return [
            'id' => $this->id,
            'course_offering_id' => $this->id,
            'course_code' => $this->unit?->code,
            'course_name' => $this->unit?->name,
            'credit_hours' => $this->unit?->credit_points,
            'semester' => $this->semester?->name,
            'semester_code' => $this->semester?->code,
            'section_code' => $this->section_code,
            'instructor_name' => $this->lecture ? trim($this->lecture->first_name.' '.$this->lecture->last_name) : null,
            'delivery_mode' => $this->delivery_mode,
            'total_students' => $totalStudents,
            'students_absent_exceeded' => $studentsAbsentExceeded,
            'absent_exceeded_percentage' => $totalStudents > 0
                ? round(($studentsAbsentExceeded / $totalStudents) * 100, 2)
                : 0.0,
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
            'max_capacity' => $this->max_capacity,
            'current_enrollment' => $this->current_enrollment,
        ];
    }
}
