<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Actions;

use App\Models\Semester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateAcademicPeriodAction
{
    /**
     * @param  array{code: string, name: string, start_date: string, end_date: string, enrollment_start_date?: string|null, enrollment_end_date?: string|null, is_active?: bool, is_archived?: bool}  $data
     * @return array{academic_period: Semester, message: string}
     */
    public static function run(array $data): array
    {
        $result = DB::transaction(function () use ($data): array {
            Semester::deactivateExpiredSemesters();

            $academicPeriod = Semester::query()->create([
                ...$data,
                'is_active' => false,
            ]);
            $message = 'Semester created successfully!';

            if ($data['is_active'] ?? false) {
                $activation = ActivateAcademicPeriodAction::run($academicPeriod);

                if (! $activation['success']) {
                    throw new AcademicPeriodOperationException($activation['message']);
                }

                $message = 'Semester created and activated successfully!';
            }

            return [
                'academic_period' => $academicPeriod,
                'message' => $message,
            ];
        });

        Log::info('Created Academic Catalog period', [
            'academic_period_id' => $result['academic_period']->id,
            'academic_period_code' => $result['academic_period']->code,
        ]);
        Cache::tags(['semesters'])->flush();

        return $result;
    }
}
