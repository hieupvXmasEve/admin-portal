<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Requests;

use App\Enums\StudentActionType;
use App\Modules\Academic\Support\StudentCodeParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class BulkStudentDecisionStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action_type' => ['required', 'string', new Enum(StudentActionType::class)],
            'student_codes' => ['required', 'string', 'max:20000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $codes = $this->studentCodes();

            if ($codes === []) {
                $validator->errors()->add('student_codes', 'At least one student code is required.');

                return;
            }

            if (count($codes) > StudentCodeParser::MAX_CODES) {
                $validator->errors()->add(
                    'student_codes',
                    sprintf('A maximum of %d unique student codes can be processed at once.', StudentCodeParser::MAX_CODES)
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    public function studentCodes(): array
    {
        return StudentCodeParser::unique((string) $this->input('student_codes', ''));
    }

    public function inputCodeCount(): int
    {
        return count(StudentCodeParser::tokens((string) $this->input('student_codes', '')));
    }

    public function actionType(): string
    {
        return (string) $this->input('action_type');
    }
}
