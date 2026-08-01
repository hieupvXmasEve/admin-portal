<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustWalletBalanceRequest extends FormRequest
{
    /**
     * Minting/burning Gold is gated by the adjust_gold_wallet permission.
     * (The route also carries can:adjust_gold_wallet; campus scoping is layered
     * on in a later phase via CampusPermissionReader.)
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('adjust_gold_wallet');
    }

    /**
     * Gold is an integer currency.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'integer',
                'not_in:0',
                'between:-999999,999999',
            ],
            'notes' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required.',
            'amount.integer' => 'Amount must be a whole number of gold.',
            'amount.not_in' => 'Amount cannot be zero.',
            'amount.between' => 'Amount must be between -999,999 and 999,999.',
            'notes.required' => 'Notes are required for balance adjustments.',
            'notes.string' => 'Notes must be a valid string.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if (isset($validated['amount'])) {
            $validated['amount'] = (int) $validated['amount'];
        }

        return $validated;
    }
}
