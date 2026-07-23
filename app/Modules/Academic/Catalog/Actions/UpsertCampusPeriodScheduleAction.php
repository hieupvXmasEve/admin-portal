<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Semester;
use App\Modules\Academic\Catalog\Models\CampusPeriodSchedule;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertCampusPeriodScheduleAction
{
    public function __construct(private readonly CampusReferenceReader $campuses) {}

    /** @param array{campus_id: int, operating_start_date?: string|null, operating_end_date?: string|null, registration_start_date?: string|null, registration_end_date?: string|null} $data */
    public function handle(Semester $academicPeriod, array $data): CampusPeriodSchedule
    {
        $campusId = (int) $data['campus_id'];
        if (! in_array($campusId, array_map(fn ($campus): int => $campus->id, $this->campuses->all()), true)) {
            throw ValidationException::withMessages(['campus_id' => 'The selected campus does not exist.']);
        }

        return DB::transaction(fn (): CampusPeriodSchedule => CampusPeriodSchedule::query()->updateOrCreate(
            ['semester_id' => $academicPeriod->id, 'campus_id' => $campusId],
            [
                'operating_start_date' => $data['operating_start_date'] ?? null,
                'operating_end_date' => $data['operating_end_date'] ?? null,
                'registration_start_date' => $data['registration_start_date'] ?? null,
                'registration_end_date' => $data['registration_end_date'] ?? null,
            ],
        ));
    }
}
