<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddManualEventParticipantsRequest extends FormRequest
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
            'student_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_ids.*' => ['required', 'integer'],
            'status' => ['required', 'in:registered,completed'],
            'bonus_gold_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
