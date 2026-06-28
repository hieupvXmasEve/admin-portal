<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Models\AiChatRun;

class StaffCopilotAnswerPresenter
{
    public function safeMarkdown(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = (string) preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $content);
        $content = (string) preg_replace('/```[\s\S]*?```/m', 'Omitted unsafe technical details.', $content);
        $content = (string) preg_replace('/~~~[\s\S]*?~~~/m', 'Omitted unsafe technical details.', $content);
        $content = (string) preg_replace('/!\[[^\]]*]\([^)]+\)/', '', $content);
        $content = (string) preg_replace('/\[([^\]]+)]\((?:https?:)?\/\/[^)]+\)/i', '$1', $content);
        $content = (string) preg_replace('/https?:\/\/\S+/i', '', $content);
        $content = (string) preg_replace('/<[^>\n]+>/', '', $content);

        $lines = [];

        foreach (explode("\n", $content) as $line) {
            if ($this->isUnsafeLine($line)) {
                continue;
            }

            $lines[] = rtrim($line);
        }

        $content = implode("\n", $lines);
        $content = (string) preg_replace("/\n{3,}/", "\n\n", $content);

        return trim($content) !== '' ? trim($content) : 'The answer was prepared safely, but no displayable text was available.';
    }

    /**
     * @return array{kind: string, title: string, message: string, safe_error_code: string|null, is_retryable: bool}
     */
    public function terminalState(string $status, ?string $safeErrorCode): array
    {
        $kind = $this->terminalKind($status, $safeErrorCode);

        return [
            'kind' => $kind,
            'title' => $this->terminalTitle($kind),
            'message' => $this->terminalMessage($kind, $safeErrorCode),
            'safe_error_code' => $safeErrorCode,
            'is_retryable' => $kind === 'failed',
        ];
    }

    /**
     * @param  list<string>  $hiddenSections
     */
    public function hiddenSectionNotice(array $hiddenSections): ?string
    {
        return $hiddenSections === []
            ? null
            : 'Some details are withheld because Staff Copilot only shows approved, safe answer content.';
    }

    private function terminalKind(string $status, ?string $safeErrorCode): string
    {
        if ($status === AiChatRun::STATUS_CANCELLED || $safeErrorCode === 'run_cancelled') {
            return 'cancelled';
        }

        if ($safeErrorCode === 'unsupported_staff_question') {
            return 'unsupported';
        }

        if (in_array($status, [AiChatRun::STATUS_COMPLETED, 'completed'], true)) {
            return 'completed';
        }

        if ($status === 'partial') {
            return 'partial';
        }

        if ($status === 'denied') {
            return 'denied';
        }

        return 'failed';
    }

    private function terminalTitle(string $kind): string
    {
        return match ($kind) {
            'completed' => 'Answer ready',
            'partial' => 'Partial answer',
            'unsupported' => 'Not supported yet',
            'cancelled' => 'Run cancelled',
            'denied' => 'Access limited',
            default => 'Could not complete',
        };
    }

    private function terminalMessage(string $kind, ?string $safeErrorCode): string
    {
        return match ($kind) {
            'completed' => 'The answer was produced from approved Swinx data.',
            'partial' => 'The answer was produced with some limits. Review the notes before relying on it.',
            'unsupported' => 'I cannot answer that in Staff Copilot yet. It only supports query-only questions over approved Swinx data.',
            'cancelled' => 'This run was cancelled before a complete answer was produced.',
            'denied' => 'I do not have access to show that answer with your current permissions.',
            default => $safeErrorCode === 'provider_invocation_failed'
                ? 'The connected AI provider was unavailable, so the run stopped safely.'
                : 'The AI copilot run could not complete safely. No raw provider, SQL, or source details were shared.',
        };
    }

    private function isUnsafeLine(string $line): bool
    {
        foreach ($this->unsafeLinePatterns() as $pattern) {
            if (preg_match($pattern, $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function unsafeLinePatterns(): array
    {
        return [
            '/\b(raw_provider_request|raw_provider_response|provider_request_body|provider_response_body|raw_sql|raw_rows|unredacted_tool|encrypted_api_key)\b/i',
            '/\b(raw\s+provider|provider\s+payload|raw\s+sql|raw\s+rows|source\s+rows)\b/i',
            '/\b(api_key|authorization|bearer_token|access_token|refresh_token)\b/i',
            '/\bselect\s+.+\bfrom\b/i',
            '/\binsert\s+into\b/i',
            '/\bupdate\s+\w+\s+set\b/i',
            '/\bdelete\s+from\b/i',
        ];
    }
}
