<?php

declare(strict_types=1);

namespace App\Modules\Upload\Http\Requests\Upload;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ListUploadsRequest extends FormRequest
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
            'context' => ['nullable', 'string', Rule::in(array_keys(array_filter(config('uploads.contexts'), static fn (array $config): bool => ! ($config['internal'] ?? false))))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $message = $validator->errors()->first();

        throw new HttpResponseException(new JsonResponse([
            'success' => false,
            'message' => 'Failed to retrieve uploads',
            'errors' => [$message],
        ], Response::HTTP_INTERNAL_SERVER_ERROR));
    }
}
