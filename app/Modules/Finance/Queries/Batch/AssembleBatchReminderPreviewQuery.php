<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Batch;

use App\Models\ParentProfile;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Illuminate\Pagination\LengthAwarePaginator;

class AssembleBatchReminderPreviewQuery
{
    public function __construct(private readonly ListDueItemsQuery $dueItems) {}

    /**
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    public function handle(string $recipient, ?int $semesterId, ?int $campusId): array
    {
        if ($campusId !== null) {
            app()->singleton('campus', fn () => (object) ['id' => $campusId]);
        }

        request()->merge(['per_page' => 500]);
        $paginator = $this->dueItems->handle($semesterId, null, '');
        $rows = $paginator instanceof LengthAwarePaginator ? $paginator->items() : [];

        $parentEmailByStudent = $recipient === 'parent'
            ? $this->parentEmailLookup(array_map(fn (array $row) => (int) ($row['student_id'] ?? 0), $rows))
            : [];

        $lines = [];
        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? 'dng_request');
            $id = (int) ($row['id'] ?? 0);
            $itemKey = $type.':'.$id;
            $lastReminderAt = $row['last_reminder_at'] ?? null;
            $studentId = (int) ($row['student_id'] ?? 0);

            $hasEmail = $recipient === 'parent'
                ? (bool) ($parentEmailByStudent[$studentId] ?? false)
                : is_string($row['student_email'] ?? null) && trim((string) $row['student_email']) !== '';

            $recentlyReminded = $lastReminderAt !== null
                && now()->parse($lastReminderAt)->gt(now()->subDay());

            $bucket = match (true) {
                ! $hasEmail => 'skip',
                $recentlyReminded => 'warning',
                default => 'create',
            };

            $lines[] = new BatchPreviewLine(
                key: 'reminder:'.$itemKey,
                hashPayload: [
                    'item' => $itemKey,
                    'recipient' => $recipient,
                    'last_reminder_at' => $lastReminderAt,
                ],
                display: [
                    'label' => (string) ($row['student_name'] ?? ''),
                    'student_id' => (string) ($row['student_code'] ?? ''),
                    'diff' => $bucket,
                    'net' => (float) ($row['balance'] ?? 0),
                    'reason' => match ($bucket) {
                        'skip' => 'no_email',
                        'warning' => 'recently_reminded',
                        default => null,
                    },
                    'warning_codes' => $recentlyReminded ? ['recently_reminded'] : [],
                ],
            );
        }

        return ['lines' => $lines, 'summary' => ['total_recipients' => count($lines)]];
    }

    /**
     * @param  int[]  $studentIds
     * @return array<int, bool>
     */
    private function parentEmailLookup(array $studentIds): array
    {
        $studentIds = array_values(array_filter(array_unique($studentIds)));

        if ($studentIds === []) {
            return [];
        }

        return ParentProfile::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->pluck('student_id')
            ->mapWithKeys(fn (int $id) => [$id => true])
            ->all();
    }
}