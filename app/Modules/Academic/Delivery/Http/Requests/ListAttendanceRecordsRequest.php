<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListAttendanceRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'session_id' => ['nullable', 'integer', 'exists:class_sessions,id'],
            'recording_method' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'participation_score_min' => ['nullable', 'numeric'],
            'participation_score_max' => ['nullable', 'numeric'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
