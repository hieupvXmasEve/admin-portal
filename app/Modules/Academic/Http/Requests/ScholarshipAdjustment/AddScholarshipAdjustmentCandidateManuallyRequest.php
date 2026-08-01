<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests\ScholarshipAdjustment;

use App\Models\Student;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\FormRequest;

class AddScholarshipAdjustmentCandidateManuallyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The operator names the student by MSSV, not by campus — resolve the
        // STUDENT's actual campus and re-verify the permission there. Trusting
        // a session campus (or no campus check at all) would let a campus-A
        // operator manually add a candidate for a campus-B student.
        $student = Student::query()->where('student_id', $this->input('student_code'))->first();

        if ($student === null) {
            // Let the `exists` rule in rules() produce the real validation error.
            return true;
        }

        $codes = app(CampusPermissionReader::class)
            ->permissionCodesForUserId((int) $this->user()->id, (int) $student->campus_id);

        return in_array('manage_scholarship_adjustment_candidate', $codes, true);
    }

    public function rules(): array
    {
        return [
            'student_code' => ['required', 'string', 'max:50', 'exists:students,student_id'],
            'source_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'target_semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'exception_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
