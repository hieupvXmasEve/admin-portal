<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FailedStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student?->student_id,
            'student_name' => $this->student?->full_name,
            'student_email' => $this->student?->email,
            'program_name' => $this->program?->name,
            'program_code' => $this->program?->code,
            'campus_name' => $this->campus?->name,
            'unit_code' => $this->unit?->code,
            'unit_name' => $this->unit?->name,
            'course_offering_section' => $this->courseOffering?->section_code ?? 'N/A',
            'lecturer_name' => $this->courseOffering?->lecture
                ? trim($this->courseOffering->lecture->first_name.' '.$this->courseOffering->lecture->last_name)
                : 'Unassigned',
            'final_percentage' => $this->final_percentage ? round((float) $this->final_percentage, 2) : 0.0,
            'final_letter_grade' => $this->final_letter_grade ?? 'F',
            'attendance_percentage' => $this->attendance_percentage ? round((float) $this->attendance_percentage, 2) : 0.0,
            'attempt_number' => $this->attempt_number ?? 1,
            'is_repeat_course' => (bool) $this->is_repeat_course,
            'retake_eligible' => $this->isRetakeEligible(),
            'semester_name' => $this->semester?->name,
            'semester_code' => $this->semester?->code,
        ];
    }

    /**
     * Determine if student is eligible for retake
     * Typically eligible if attempt_number <= 2
     */
    private function isRetakeEligible(): bool
    {
        return ($this->attempt_number ?? 1) <= 2;
    }
}
