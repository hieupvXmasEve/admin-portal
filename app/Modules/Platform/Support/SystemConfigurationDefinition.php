<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SystemConfigurationDefinition
{
    /**
     * Runtime values live exclusively in system_settings. This registry owns
     * their supported keys, types, and exposure policy—not mutable defaults.
     *
     * @var array<string, array{public: bool, rules: list<string>}>
     */
    private const DEFINITIONS = [
        'app_name' => [
            'public' => true,
            'rules' => ['string', 'max:255'],
        ],
        'copyright_text' => [
            'public' => true,
            'rules' => ['string', 'max:500'],
        ],
        'country' => [
            'public' => true,
            'rules' => ['string', 'max:100'],
        ],
        'survey_enabled' => [
            'public' => true,
            'rules' => ['boolean'],
        ],
        'default_course_survey' => [
            'public' => false,
            'rules' => ['nullable', 'integer', 'min:1'],
        ],
        'active_query_forms' => [
            'public' => false,
            'rules' => ['array'],
        ],
        'system_booking_start_time' => [
            'public' => false,
            'rules' => ['date_format:H:i'],
        ],
        'system_booking_end_time' => [
            'public' => false,
            'rules' => ['date_format:H:i'],
        ],
        'allow_student_booking' => [
            'public' => false,
            'rules' => ['boolean'],
        ],
        'student_booking_limit_per_day' => [
            'public' => false,
            'rules' => ['integer', 'min:0'],
        ],
        'logo_full_upload_id' => [
            'public' => false,
            'rules' => ['nullable', 'integer', 'min:1'],
        ],
        'logo_text_upload_id' => [
            'public' => false,
            'rules' => ['nullable', 'integer', 'min:1'],
        ],
        'favicon_upload_id' => [
            'public' => false,
            'rules' => ['nullable', 'integer', 'min:1'],
        ],
        'apple_touch_icon_upload_id' => [
            'public' => false,
            'rules' => ['nullable', 'integer', 'min:1'],
        ],
    ];

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return list<string> */
    public function requiredKeys(): array
    {
        return $this->keys();
    }

    /** @return list<string> */
    public function publicKeys(): array
    {
        return array_keys(array_filter(
            self::DEFINITIONS,
            static fn (array $definition): bool => $definition['public'],
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    public function validationRules(): array
    {
        $rules = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $rules[$key] = ['sometimes', ...$definition['rules']];
        }

        $rules['active_query_forms.*'] = ['integer', 'min:1', 'distinct'];

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $attributes): array
    {
        $unknownKeys = array_values(array_diff(array_keys($attributes), $this->keys()));
        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'configuration' => ['Unsupported system configuration keys: '.implode(', ', $unknownKeys).'.'],
            ]);
        }

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($attributes, $this->validationRules())->validate();

        return $validated;
    }
}
