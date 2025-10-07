<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('wallets.deposit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'min:1000', // Minimum 1,000 VND
                'max:100000000', // Maximum 100,000,000 VND
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Deposit amount is required.',
            'amount.numeric' => 'Deposit amount must be a valid number.',
            'amount.min' => 'Minimum deposit amount is 1,000 VND.',
            'amount.max' => 'Maximum deposit amount is 100,000,000 VND.',
            'description.max' => 'Description cannot exceed 255 characters.',
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'amount' => 'deposit amount',
            'description' => 'description',
        ];
    }
}
