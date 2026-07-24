<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;

class SaveGradebookScoresRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.detail_id' => ['required', 'integer', 'exists:assessment_component_details,id'],
            'scores.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'scores.*.points_earned' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $courseOffering = $this->route('courseOffering');
            if (! $courseOffering instanceof CourseOffering) {
                return;
            }

            foreach ($this->resolveScores($courseOffering, $this->input('scores', []))['errors'] as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }

    /**
     * @return array<int, array{detail:AssessmentComponentDetail, student_id:int, points_earned:float, max_points:float}>
     */
    public function gradebookScores(CourseOffering $courseOffering): array
    {
        return $this->resolveScores($courseOffering, $this->validated('scores'))['scores'];
    }

    /**
     * @param  array<int, array{detail_id:int, student_id:int, points_earned:int|float|string}>  $scores
     * @return array{scores:array<int, array{detail:AssessmentComponentDetail, student_id:int, points_earned:float, max_points:float}>, errors:array<string, list<string>>}
     */
    private function resolveScores(CourseOffering $courseOffering, array $scores): array
    {
        $syllabus = $courseOffering->syllabusTemplate;
        if ($syllabus === null) {
            return ['scores' => [], 'errors' => ['course_offering' => ['Course offering does not have a syllabus template.']]];
        }

        $details = AssessmentComponentDetail::query()->with('assessmentComponent')
            ->whereIn('id', collect($scores)->pluck('detail_id')->unique())
            ->get()->keyBy('id');
        $eligibleStudentIds = CourseRegistration::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('registration_status', ['registered', 'confirmed', 'completed'])
            ->whereIn('student_id', collect($scores)->pluck('student_id')->unique())
            ->pluck('student_id')->flip();

        $resolved = [];
        $errors = [];
        $seenCells = [];
        foreach ($scores as $index => $scoreData) {
            $detailId = (int) $scoreData['detail_id'];
            $studentId = (int) $scoreData['student_id'];
            $pointsEarned = (float) $scoreData['points_earned'];
            $cellKey = "{$detailId}:{$studentId}";
            $detail = $details->get($detailId);
            $maxPoints = $detail?->max_points === null || $detail?->max_points === '' ? 100.0 : (float) $detail->max_points;

            if (isset($seenCells[$cellKey])) {
                $errors["scores.{$index}.detail_id"][] = 'Duplicate score cell in request.';
            } elseif ($detail === null || $detail->assessmentComponent === null) {
                $errors["scores.{$index}.detail_id"][] = 'Assessment detail was not found.';
            } elseif ((int) $detail->assessmentComponent->syllabus_template_id !== (int) $syllabus->id) {
                $errors["scores.{$index}.detail_id"][] = 'Assessment detail does not belong to this course offering syllabus.';
            } elseif (! $eligibleStudentIds->has($studentId)) {
                $errors["scores.{$index}.student_id"][] = 'Student is not eligible to receive assessment evidence for this course offering.';
            } elseif ($maxPoints <= 0) {
                $errors["scores.{$index}.detail_id"][] = 'Assessment detail is not gradable because max points is zero.';
            } elseif ($pointsEarned > $maxPoints) {
                $errors["scores.{$index}.points_earned"][] = "Points earned may not be greater than {$maxPoints}.";
            } else {
                $resolved[] = ['detail' => $detail, 'student_id' => $studentId, 'points_earned' => $pointsEarned, 'max_points' => $maxPoints];
            }
            $seenCells[$cellKey] = true;
        }

        return ['scores' => $resolved, 'errors' => $errors];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }
}
