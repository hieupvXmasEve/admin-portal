<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Semester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateAcademicPeriodAction
{
    /**
     * @param  array{code: string, name: string, start_date: string, end_date: string, enrollment_start_date?: string|null, enrollment_end_date?: string|null, is_active?: bool, is_archived?: bool}  $data
     * @return array{academic_period: Semester, message: string}
     */
    public static function run(Semester $academicPeriod, array $data): array
    {
        $result = DB::transaction(function () use ($academicPeriod, $data): array {
            if (Semester::query()
                ->where('name', $data['name'])
                ->where('id', '!=', $academicPeriod->id)
                ->exists()) {
                throw new AcademicPeriodOperationException('A semester with this name already exists.');
            }

            Semester::deactivateExpiredSemesters();
            $wasActive = (bool) $academicPeriod->is_active;
            $wantsToBeActive = $data['is_active'] ?? false;
            $academicPeriod->update([...$data, 'is_active' => $wasActive]);
            $message = 'Semester updated successfully!';

            if ($wasActive !== $wantsToBeActive) {
                $statusChange = $wantsToBeActive
                    ? ActivateAcademicPeriodAction::run($academicPeriod)
                    : DeactivateAcademicPeriodAction::run($academicPeriod);

                if (! $statusChange['success']) {
                    throw new AcademicPeriodOperationException($statusChange['message']);
                }

                $message = $wantsToBeActive
                    ? 'Semester updated and activated successfully!'
                    : 'Semester updated and deactivated successfully!';
            }

            return ['academic_period' => $academicPeriod->fresh(), 'message' => $message];
        });

        Log::info('Updated Academic Catalog period', [
            'academic_period_id' => $academicPeriod->id,
            'academic_period_code' => $academicPeriod->code,
        ]);
        Cache::tags(['semesters'])->flush();

        return $result;
    }
}
