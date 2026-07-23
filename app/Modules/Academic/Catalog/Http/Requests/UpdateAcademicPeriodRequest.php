<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Requests;

use App\Models\Semester;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademicPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Semester $semester */
        $semester = $this->route('semester');

        return AcademicPeriodRequestRules::update($semester);
    }
}
