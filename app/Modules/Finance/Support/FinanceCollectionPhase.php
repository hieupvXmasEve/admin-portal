<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Semester;
use Carbon\Carbon;

/**
 * Infers an early/mid/late collection phase from the active semester (and the
 * active billing cycle, if any). Pure read; the operator can override via a
 * session value the cockpit controller passes in.
 */
class FinanceCollectionPhase
{
    /** @return array{key:string,label:string,source:string} */
    public static function infer(?string $override = null): array
    {
        if (in_array($override, ['early', 'mid', 'late'], true)) {
            return self::label($override, 'manual');
        }

        $semester = Semester::getActiveSemester();
        if ($semester === null || $semester->start_date === null || $semester->end_date === null) {
            return self::label('mid', 'default');
        }

        $start = Carbon::parse($semester->start_date);
        $end = Carbon::parse($semester->end_date);
        $now = Carbon::now();
        $total = max(1, $start->diffInDays($end));
        $elapsed = max(0, $start->diffInDays($now));
        $pct = min(100, (int) round(($elapsed / $total) * 100));

        $key = $pct <= 33 ? 'early' : ($pct >= 66 ? 'late' : 'mid');

        return self::label($key, 'inferred');
    }

    /** @return array{key:string,label:string,source:string} */
    private static function label(string $key, string $source): array
    {
        $labels = ['early' => 'Đầu kỳ', 'mid' => 'Giữa kỳ', 'late' => 'Cuối kỳ'];

        return ['key' => $key, 'label' => $labels[$key] ?? 'Giữa kỳ', 'source' => $source];
    }
}