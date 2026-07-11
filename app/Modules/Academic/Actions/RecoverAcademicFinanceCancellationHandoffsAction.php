<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Jobs\DispatchAcademicFinanceCancellationHandoffJob;
use App\Modules\Academic\Models\AcademicFinanceCancellationHandoff;

class RecoverAcademicFinanceCancellationHandoffsAction
{
    /** @return list<int> */
    public function handle(int $limit = 100): array
    {
        $ids = AcademicFinanceCancellationHandoff::query()
            ->whereIn('status', [
                AcademicFinanceCancellationHandoff::STATUS_PENDING,
                AcademicFinanceCancellationHandoff::STATUS_FAILED,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($ids as $id) {
            DispatchAcademicFinanceCancellationHandoffJob::dispatch($id);
        }

        return $ids;
    }

    /** @param array{limit?: int} $data @return list<int> */
    public static function run(array $data): array
    {
        return app(self::class)->handle((int) ($data['limit'] ?? 100));
    }
}
