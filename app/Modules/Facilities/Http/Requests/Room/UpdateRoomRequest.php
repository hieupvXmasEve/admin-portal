<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Requests\Room;

use App\Modules\Facilities\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('edit_room');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('rooms', 'code')
                    ->where('campus_id', app('campus')->id)
                    ->ignore($room->id),
            ],
            'building_id' => ['required', 'integer', Rule::exists('buildings', 'id')->where('campus_id', app('campus')->id)],
            'floor' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', Rule::in(Room::getTypes())],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', 'string', Rule::in(Room::getStatuses())],
            'is_bookable' => ['boolean'],
            'requires_approval' => ['boolean'],
            'available_from' => ['nullable', 'date_format:H:i:s'],
            'available_until' => ['nullable', 'date_format:H:i:s'],
            'blocked_days' => ['nullable', 'array'],
            'blocked_days.*' => ['string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'description' => ['nullable', 'string', 'max:1000'],
            'usage_guidelines' => ['nullable', 'string', 'max:2000'],
            'booking_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'room name',
            'code' => 'room code',
            'building_id' => 'building',
            'floor' => 'floor',
            'type' => 'room type',
            'capacity' => 'capacity',
            'status' => 'status',
            'is_bookable' => 'bookable',
            'requires_approval' => 'requires approval',
            'available_from' => 'available from time',
            'available_until' => 'available until time',
            'blocked_days' => 'blocked days',
            'description' => 'description',
            'usage_guidelines' => 'usage guidelines',
            'booking_notes' => 'booking notes',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'A room with this code already exists in the current campus.',
            'type.in' => 'The selected room type is invalid.',
            'status.in' => 'The selected status is invalid.',
            'available_from.date_format' => 'The available from time must be in HH:MM:SS format.',
            'available_until.date_format' => 'The available until time must be in HH:MM:SS format.',
            'blocked_days.*.in' => 'Invalid day of the week selected.',
        ];
    }
}
