<?php

declare(strict_types=1);

namespace App\Modules\Notification\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Mews\Purifier\Facades\Purifier;

/**
 * Validates admin edits to a notification email template.
 *
 * Authorization: delegates to NotificationTemplatePolicy::update (B3).
 * Variable allow-list: rejects any {{var}} token not in the template's
 *   type_key->availableVariables() keys (D8).
 * Sanitization: runs Purifier::clean on body_html in passedValidation (B2).
 */
class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $template = $this->route('template');

        if ($user === null || $template === null) {
            return false;
        }

        return $user->hasSystemRole('super_admin');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:500'],
            'body_html' => ['required', 'string', 'min:1', 'max:65535'],
        ];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $validator) {
            $template = $this->route('template');

            if ($template === null) {
                return;
            }

            /** @var array<string, array{label: string, sample: mixed}> $variables */
            $variables = $template->type_key->availableVariables();
            $allowed = array_keys($variables);

            $fields = [
                'subject' => (string) $this->input('subject', ''),
                'body_html' => (string) $this->input('body_html', ''),
            ];

            foreach ($fields as $field => $value) {
                preg_match_all('/\{\{(\w+)\}\}/', $value, $matches);

                foreach ($matches[1] as $varName) {
                    if (! in_array($varName, $allowed, true)) {
                        $validator->errors()->add(
                            $field,
                            "Unknown template variable: {{{$varName}}}. Allowed: ".implode(', ', $allowed).'.',
                        );
                    }
                }
            }
        });
    }

    public function passedValidation(): void
    {
        $this->merge([
            'body_html' => Purifier::clean($this->input('body_html'), 'email_body'),
        ]);
    }
}
