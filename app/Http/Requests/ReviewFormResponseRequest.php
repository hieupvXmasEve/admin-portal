<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\FormResponse;

class ReviewFormResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        // Check if user has review permissions for this response
        $response = FormResponse::findOrFail($this->route('response'));
        return $response->canBeReviewedBy(auth()->user());
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Please specify whether to approve or reject the response.',
            'action.in' => 'The action must be either approve or reject.',
            'notes.max' => 'Review notes cannot exceed 1000 characters.',
        ];
    }
}