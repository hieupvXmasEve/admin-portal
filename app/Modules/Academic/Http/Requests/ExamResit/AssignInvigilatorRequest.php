<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ExamResit;

use Illuminate\Foundation\Http\FormRequest;

class AssignInvigilatorRequest extends FormRequest
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
            'lecture_id' => ['required', 'integer', 'exists:lectures,id'],
            'role' => ['nullable', 'string', 'in:lead,assistant,backup'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
