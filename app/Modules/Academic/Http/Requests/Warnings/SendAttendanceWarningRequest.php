<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Warnings;

use Illuminate\Foundation\Http\FormRequest;

class SendAttendanceWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('send_manual_notification') && $user->can('view_attendance');
    }

    public function rules(): array
    {
        return [];
    }
}
