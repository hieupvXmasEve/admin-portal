<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\Room;

use App\Modules\Facilities\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_room') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:'.implode(',', Room::getTypes())],
            'status' => ['nullable', 'string', 'in:'.implode(',', Room::getStatuses())],
            'building_id' => [
                'nullable',
                'integer',
                Rule::exists('buildings', 'id')->where('campus_id', app('campus')->id),
            ],
            'floor' => ['nullable', 'string', 'max:255'],
            'is_bookable' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'min_capacity' => ['nullable', 'integer', 'min:1'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:name,building_id,type,capacity,status,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
