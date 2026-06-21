<?php

declare(strict_types=1);

namespace App\Http\Requests\SyllabusTemplate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the transport shape of a grading-scheme preview request. The
 * engine-specific contract is checked by GradingSchemeValidator inside
 * PreviewSyllabusGradingSchemeAction, so this request only guards structure and
 * authorization.
 */
final class PreviewGradingSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit_syllabus') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'grading_scheme' => ['required', 'array'],
            'component_scores' => ['present', 'array'],
            'component_scores.*' => ['nullable', 'numeric'],
        ];
    }
}
