<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BulkUpdateEventParticipantsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'participant_ids' => ['required', 'array', 'min:1', 'max:50'],
            'participant_ids.*' => ['required', 'integer', 'exists:event_participants,id'],
            'status' => ['required', 'in:registered,completed,cancelled'],
        ];
    }
}
