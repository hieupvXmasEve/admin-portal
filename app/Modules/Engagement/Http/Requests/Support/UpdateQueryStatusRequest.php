<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Requests\Support;

use App\Models\QueryTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateQueryStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(QueryTicket::STATUSES)],
        ];
    }
}
