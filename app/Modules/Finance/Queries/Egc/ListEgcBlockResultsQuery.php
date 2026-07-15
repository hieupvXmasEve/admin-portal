<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Egc;

use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ListEgcBlockResultsQuery
{
    public function handle(int $semesterId, array $filters = [], ?int $campusId = null): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $result = (string) ($filters['result'] ?? 'all');

        $rows = collect(app(AcademicFinanceChargeSourceGateway::class)->egcBlocksForSemester($semesterId))
            ->when($campusId !== null, fn (Collection $items) => $items->where('campus_id', $campusId))
            ->when($search !== '', fn (Collection $items) => $items->filter(
                fn (AcademicEgcBlockData $block): bool => str_contains(mb_strtolower((string) $block->student_name), mb_strtolower($search))
                    || str_contains(mb_strtolower((string) $block->student_code), mb_strtolower($search))
            ))
            ->when($result !== 'all', fn (Collection $items) => $items->where('result', $result))
            ->values()
            ->map(fn (AcademicEgcBlockData $block): array => $this->row($block));

        return $this->paginate($rows, (int) ($filters['per_page'] ?? 50));
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AcademicEgcBlockData $block): array
    {
        return [
            'id' => $block->id,
            'student_id' => $block->student_id,
            'student' => [
                'id' => $block->student_id,
                'full_name' => $block->student_name,
                'student_id' => $block->student_code,
            ],
            'block_number' => $block->block_number,
            'level_number' => $block->level_number,
            'result' => $block->result,
            'attendance_rate' => $block->attendance_rate === null ? null : (string) $block->attendance_rate,
            'is_retake' => $block->is_retake,
            'synced_at' => $block->synced_at,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginate(Collection $rows, int $perPage): LengthAwarePaginator
    {
        $perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 50;
        $page = max(1, (int) request()->get('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
