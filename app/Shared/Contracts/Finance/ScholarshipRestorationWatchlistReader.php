<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

use App\Shared\Contracts\Finance\DTO\ScholarshipRestorationWatchlistRow;
use Illuminate\Support\Collection;

/**
 * Students carrying a scholarship adjustment forward, for the restoration
 * watchlist (Phase 3). Finance-owned — Academic composes this with verdict,
 * GPA, attendance, and course data without ever importing Finance internals.
 */
interface ScholarshipRestorationWatchlistReader
{
    /** @return Collection<int, ScholarshipRestorationWatchlistRow> */
    public function listCarried(int $campusId): Collection;
}
