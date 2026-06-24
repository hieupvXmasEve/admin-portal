<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitStaffCopilotMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view_ai_metrics');
    }

    public function rules(): array
    {
        return [
            'conversation_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function question(): string
    {
        return trim((string) $this->validated('question'));
    }

    public function conversationId(): ?int
    {
        $conversationId = $this->validated('conversation_id');

        if ($conversationId === null || $conversationId === '') {
            return null;
        }

        return (int) $conversationId;
    }
}
