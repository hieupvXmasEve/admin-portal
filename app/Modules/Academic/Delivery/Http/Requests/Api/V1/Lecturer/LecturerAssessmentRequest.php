<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Requests\Api\V1\Lecturer;

use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Shared\Contracts\Identity\LecturerTeachingActor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class LecturerAssessmentRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors()->toArray())
        );
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            ApiResponse::authorizationError('Unauthorized access to course offering')
        );
    }

    protected function canAccessBoundCourseOffering(): bool
    {
        $courseOffering = $this->route('courseOffering');
        $lecturer = $this->user();

        return $courseOffering instanceof CourseOffering
            && $lecturer instanceof LecturerTeachingActor
            && $courseOffering->classSessions()
                ->where('lecture_id', $lecturer->lecturerId())
                ->exists();
    }
}
