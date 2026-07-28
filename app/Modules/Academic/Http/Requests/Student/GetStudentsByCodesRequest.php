<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class GetStudentsByCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_ids' => 'required|array',
            'student_ids.*' => 'required|string',
        ];
    }
}
