<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Pricing;

use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePricingRuleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_finance_pricing_operations') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $catalogTypes = ObligationTypeRegistry::typesRequiringPricingCatalog();

        return [
            'obligation_type' => ['required', 'string', 'max:50', Rule::in($catalogTypes)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'rule_version' => [
                'required',
                'string',
                'max:100',
                Rule::unique('finance_pricing_catalog_items', 'rule_version')
                    ->where(fn ($query) => $query->where('obligation_type', $this->input('obligation_type'))),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'facts_match_json' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $raw = $this->input('facts_match_json');

            if ($raw === null || $raw === '') {
                return;
            }

            if (! is_string($raw)) {
                $validator->errors()->add('facts_match_json', 'facts_match must be a JSON object string.');

                return;
            }

            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $validator->errors()->add('facts_match_json', 'facts_match must be valid JSON.');

                return;
            }

            if (! is_array($decoded) || array_is_list($decoded)) {
                $validator->errors()->add('facts_match_json', 'facts_match must be a JSON object (key/value map), not an array.');
            }
        });
    }

    /**
     * @return array{
     *     obligation_type: string,
     *     amount: float,
     *     currency: string,
     *     rule_version: string,
     *     description: string|null,
     *     facts_match: array<string, mixed>|null,
     *     is_active: bool,
     *     effective_from: string|null,
     *     effective_until: string|null,
     * }
     */
    public function pricingPayload(): array
    {
        $factsMatch = null;
        $raw = $this->input('facts_match_json');

        if (is_string($raw) && $raw !== '') {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $factsMatch = $decoded;
        }

        return [
            'obligation_type' => (string) $this->input('obligation_type'),
            'amount' => (float) $this->input('amount'),
            'currency' => strtoupper((string) ($this->input('currency') ?: 'VND')),
            'rule_version' => (string) $this->input('rule_version'),
            'description' => $this->input('description'),
            'facts_match' => $factsMatch,
            'is_active' => $this->boolean('is_active', true),
            'effective_from' => $this->input('effective_from'),
            'effective_until' => $this->input('effective_until'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'obligation_type.required' => 'Obligation type is required.',
            'obligation_type.in' => 'Selected obligation type does not use catalog pricing.',
            'amount.required' => 'Amount is required.',
            'rule_version.required' => 'Rule version is required.',
            'rule_version.unique' => 'This rule version already exists for the obligation type.',
            'effective_until.after' => 'Effective until must be after effective from.',
        ];
    }
}
