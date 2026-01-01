<?php

namespace App\Modules\Identity\Http\Requests\Identity;

use Illuminate\Foundation\Http\FormRequest;

class SelectCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selectedCampus' => [
                'required',
                'integer',
                'exists:campuses,id',
                function ($attribute, $value, $fail) {
                    if (!$this->user()->campuses()->where('campuses.id', $value)->exists()) {
                        $fail('You do not have access to this campus.');
                    }
                },
            ],
        ];
    }
}
