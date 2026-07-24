<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveClassSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course_offering_id' => ['required', 'integer', 'exists:course_offerings,id'],
            'session_title' => ['required', 'string', 'max:255'],
            'session_description' => ['nullable', 'string'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'session_type' => ['required', Rule::in(['lecture', 'tutorial', 'practical', 'workshop', 'seminar', 'exam'])],
            'delivery_mode' => ['required', Rule::in(['in_person', 'online', 'hybrid'])],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed', 'cancelled'])],
            'attendance_required' => ['boolean'],
            'attendance_tracking_enabled' => ['boolean'],
            'learning_objectives' => ['nullable', 'array'],
            'required_materials' => ['nullable', 'array'],
            'topics_covered' => ['nullable', 'array'],
            'online_meeting_url' => ['nullable', 'url'],
            'instructor_notes' => ['nullable', 'string'],
            'lecture_id' => ['nullable', 'integer', 'exists:lectures,id'],
            'room_id' => ['nullable', Rule::exists('rooms', 'id')->where('campus_id', app('campus')->id)],
        ];
    }
}
