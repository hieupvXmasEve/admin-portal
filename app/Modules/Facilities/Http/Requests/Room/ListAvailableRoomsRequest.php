<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\Room;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;

class ListAvailableRoomsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_room') ?? false;
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['nullable', 'integer', 'in:'.app('campus')->id],
            'status' => ['nullable', 'string', 'in:available,occupied,maintenance,reserved'],
            'is_bookable' => ['nullable', 'string', 'in:true,false,1,0'],
            'type' => ['nullable', 'string', 'in:'.implode(',', Room::getTypes())],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
