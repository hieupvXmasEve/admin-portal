<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Warnings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarningSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('send_manual_notification');
    }

    public function rules(): array
    {
        return [
            'attendance_warning_ratio' => ['required', 'numeric', 'min:0.1', 'max:1'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', 'in:realtime,email'],
            'academic_warning_title' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'academic_warning_body' => ['required', 'string', 'max:2000'],
            'attendance_warning_title' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'attendance_warning_body' => ['required', 'string', 'max:2000'],
            'attendance_exceeded_title' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'attendance_exceeded_body' => ['required', 'string', 'max:2000'],
        ];
    }
}
