<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ExportStudentsRequest extends FormRequest
{
    use ChecksStudentDirectoryFilters;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => 'required|string|in:xlsx,csv',
            'scope' => 'required|string|in:all,filtered',
            // Optional filters when scope is 'filtered'
            ...$this->studentFilterRules(),
        ];
    }
}
