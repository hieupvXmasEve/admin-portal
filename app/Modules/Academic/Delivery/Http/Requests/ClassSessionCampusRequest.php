<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests;

use App\Models\ClassSession;
use Illuminate\Foundation\Http\FormRequest;

final class ClassSessionCampusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classSession = $this->route('classSession');

        return $classSession instanceof ClassSession && $classSession->courseOffering->campus_id === app('campus')->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        abort(404);
    }
}
