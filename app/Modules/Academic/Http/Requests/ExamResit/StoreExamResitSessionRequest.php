<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamResitSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'exam_room_slot_id' => ['required', 'integer', 'exists:exam_room_slots,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'expected_candidates' => ['nullable', 'integer', 'min:1'],
            'syllabus_template_id' => ['nullable', 'integer', 'exists:syllabus_templates,id'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'materials_allowed' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
