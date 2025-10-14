<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayInvoiceRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // No body params needed, validation happens in withValidator
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $invoice = $this->route('invoice');
            $billingCycle = $this->route('billingCycle');
            
            if ($invoice && $billingCycle && $invoice->billing_cycle_id !== $billingCycle->id) {
                $validator->errors()->add('invoice', 'Invoice does not belong to this billing cycle.');
            }
        });
    }
}
