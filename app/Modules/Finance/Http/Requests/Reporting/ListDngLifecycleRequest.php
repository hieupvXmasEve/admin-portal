<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Reporting;

use App\Modules\Finance\Support\Reporting\DngLifecycleCatalog as Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDngLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_finance_reporting') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'view' => 'nullable|string|max:64',
            'attention_bucket' => ['nullable', 'string', Rule::in($this->withAll(Catalog::attentionBucketKeys()))],
            'dng_status' => ['nullable', 'string', Rule::in($this->withAll(Catalog::statusKeys()))],
            'payment_bridge' => ['nullable', 'string', Rule::in($this->withAll(array_keys(Catalog::paymentBridgeStates())))],
            'webhook_state' => ['nullable', 'string', Rule::in($this->withAll(array_keys(Catalog::webhookStates())))],
            'invoice_state' => ['nullable', 'string', Rule::in($this->withAll(array_keys(Catalog::invoiceStates())))],
            'allocation_state' => ['nullable', 'string', Rule::in($this->withAll(array_keys(Catalog::allocationStates())))],
            'flow_state' => ['nullable', 'string', Rule::in($this->withAll(array_keys(Catalog::flowStates())))],
            'related_semester' => 'nullable',
            'outside_selected_semester' => ['nullable', 'string', Rule::in(['all', 'outside', 'inside'])],
            'fee_type' => 'nullable|string|max:64',
            'amount_min' => 'nullable|numeric|min:0',
            'amount_max' => 'nullable|numeric|min:0',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date',
            'paid_from' => 'nullable|date',
            'paid_to' => 'nullable|date',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|in:20,50,100',
            'page' => 'nullable|integer|min:1',
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function withAll(array $keys): array
    {
        return array_merge(['all'], $keys);
    }
}
