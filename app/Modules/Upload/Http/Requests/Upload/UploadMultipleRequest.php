<?php

declare(strict_types=1);

namespace App\Modules\Upload\Http\Requests\Upload;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UploadMultipleRequest extends FormRequest
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
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file'],
            'context' => ['required', 'string', Rule::in(array_keys(array_filter(config('uploads.contexts'), static fn (array $config): bool => ! ($config['internal'] ?? false))))],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = $validator->errors()->first();

        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'Multiple upload failed: '.$message,
            'errors' => [$message],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
