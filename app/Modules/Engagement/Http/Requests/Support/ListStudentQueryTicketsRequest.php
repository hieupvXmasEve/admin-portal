<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Support;

use App\Modules\Engagement\Models\QueryTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListStudentQueryTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(QueryTicket::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
