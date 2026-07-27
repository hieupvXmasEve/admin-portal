<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RegisterStudentForActiveSemesterUnitsAction
{
    /**
     * @param  array{student_id: int, unit_ids: list<int>, notes?: string|null}  $data
     * @return array{registered_count: int, errors: list<string>}
     */
    public static function run(array $data, int $campusId): array
    {
        $student = app(StudentReferenceReader::class)->find($data['student_id']);
        if ($student === null) {
            throw new \RuntimeException('Student not found.');
        }
        if ($student->campusId !== $campusId) {
            throw new NotFoundHttpException;
        }

        $activeSemester = app(CourseOfferingCatalogReader::class)->currentOfferingPeriod();
        if ($activeSemester === null) {
            throw new \RuntimeException('No active semester found');
        }
        $activeSemesterId = $activeSemester->id;

        $registeredCount = 0;
        $errors = [];

        DB::transaction(function () use ($data, $student, $activeSemesterId, $campusId, &$registeredCount, &$errors): void {
            foreach (array_values(array_unique($data['unit_ids'])) as $unitId) {
                try {
                    if (in_array($student->status, ['inactive', 'dropout', 'dropout_transfer', 'graduated', 'pending'], true)) {
                        throw new \RuntimeException('Student is not active.');
                    }

                    $courseOffering = CourseOffering::query()
                        ->where('unit_id', $unitId)
                        ->where('semester_id', $activeSemesterId)
                        ->where('campus_id', $campusId)
                        ->where('is_active', true)
                        ->where('enrollment_status', 'open')
                        ->orderBy('current_enrollment')
                        ->first();

                    if ($courseOffering === null) {
                        $errors[] = "No available course offering found for unit ID {$unitId}";

                        continue;
                    }

                    EnrollStudentInCourseOfferingAction::run([
                        'student_id' => $student->id,
                        'course_offering_id' => $courseOffering->id,
                        'registration_status' => 'confirmed',
                        'registration_method' => 'admin_override',
                        'notes' => $data['notes'] ?? null,
                    ]);

                    $registeredCount++;
                } catch (\Throwable $exception) {
                    $errors[] = "Unit ID {$unitId}: ".$exception->getMessage();
                }
            }
        });

        return ['registered_count' => $registeredCount, 'errors' => $errors];
    }
}
