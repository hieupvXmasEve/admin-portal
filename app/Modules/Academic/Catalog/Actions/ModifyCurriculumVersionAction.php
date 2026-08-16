<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\CurriculumVersion;
use Illuminate\Support\Facades\DB;

class ModifyCurriculumVersionAction
{
    /** @param array<string, mixed> $data */
    public function update(CurriculumVersion $curriculumVersion, array $data): void
    {
        $this->ensureEditable($curriculumVersion);
        $curriculumVersion->update($data);
    }

    public function delete(CurriculumVersion $curriculumVersion): void
    {
        $this->ensureEditable($curriculumVersion);
        $curriculumVersion->delete();
    }

    /** @param array{version_code: string, semester_id?: int|null, notes?: string|null, include_curriculum_units: bool} $data */
    public function duplicate(CurriculumVersion $curriculumVersion, array $data): CurriculumVersion
    {
        return DB::transaction(function () use ($curriculumVersion, $data): CurriculumVersion {
            $duplicate = CurriculumVersion::query()->create([
                'program_id' => $curriculumVersion->program_id,
                'specialization_id' => $curriculumVersion->specialization_id,
                'version_code' => $data['version_code'],
                'semester_id' => $data['semester_id'] ?? $curriculumVersion->semester_id,
                'notes' => $data['notes'] ?? $curriculumVersion->notes,
            ]);

            if ($data['include_curriculum_units']) {
                foreach ($curriculumVersion->curriculumUnits()->get() as $curriculumUnit) {
                    $duplicate->curriculumUnits()->create([
                        'unit_id' => $curriculumUnit->unit_id,
                        'year_level' => $curriculumUnit->year_level,
                        'semester_number' => $curriculumUnit->semester_number,
                        'unit_scope' => $curriculumUnit->unit_scope,
                        'is_compulsory' => $curriculumUnit->is_compulsory,
                        'is_prerequisite_flexible' => $curriculumUnit->is_prerequisite_flexible,
                        'note' => $curriculumUnit->note,
                    ]);
                }
            }

            return $duplicate;
        });
    }

    private function ensureEditable(CurriculumVersion $curriculumVersion): void
    {
        $curriculumVersion->loadMissing('effectiveFromSemester');
        $startDate = $curriculumVersion->effectiveFromSemester?->start_date;
        if ($startDate !== null && now()->greaterThanOrEqualTo($startDate)) {
            throw new \DomainException('Cannot modify a curriculum version that is already active or has passed.');
        }
    }
}
