<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ListAcademicPeriodActivationStatusesQuery
{
    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return Cache::tags(['semesters', 'semesters.status'])
            ->remember('semesters:activation_statuses', now()->addMinute(), function (): array {
                $academicPeriods = Semester::query()->orderBy('start_date')->get();
                $now = Carbon::now();
                $activePeriod = Semester::getActiveSemester();
                $nextPeriod = Semester::getNextActiveSemester();

                return $academicPeriods->map(function (Semester $academicPeriod) use ($now, $activePeriod, $nextPeriod): array {
                    $status = 'inactive';
                    $canActivate = false;
                    $reason = '';

                    if ($academicPeriod->is_active) {
                        $status = 'active';
                        $reason = $academicPeriod->shouldBeDeactivated()
                            ? 'Expired - will be deactivated automatically'
                            : '';
                    } elseif ($academicPeriod->is_archived) {
                        $status = 'archived';
                        $reason = 'Cannot activate archived semester';
                    } elseif ($academicPeriod->start_date?->lte($now)) {
                        $status = 'started';
                        $reason = 'Cannot activate semester that has already started';
                    } elseif ($nextPeriod?->id === $academicPeriod->id) {
                        $status = 'next';
                        $canActivate = true;
                        $reason = 'This is the next semester that can be activated';
                    } else {
                        $status = 'future';
                        $reason = 'Can only activate the next upcoming semester';
                    }

                    return [
                        'semester' => $academicPeriod,
                        'status' => $status,
                        'can_activate' => $canActivate,
                        'reason' => $reason,
                        'is_current_active' => $activePeriod?->id === $academicPeriod->id,
                        'is_next_available' => $nextPeriod?->id === $academicPeriod->id,
                    ];
                })->all();
            });
    }
}
