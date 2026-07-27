<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Api\V1\Lecturer;

use App\Models\CourseOffering;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class AssessmentStatisticsReportRequest extends LecturerAssessmentRequest
{
    public function authorize(): bool
    {
        $courseOffering = $this->route('courseOffering');

        return $courseOffering instanceof CourseOffering
            && Gate::forUser($this->user())->allows('viewGradebook', $courseOffering);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(['all', 'overview', 'score_distribution', 'completion'])],
        ];
    }

    public function statisticsType(): string
    {
        return $this->validated('type', 'all');
    }
}
