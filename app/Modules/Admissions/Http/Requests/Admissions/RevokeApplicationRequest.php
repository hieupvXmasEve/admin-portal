<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

final class RevokeApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
