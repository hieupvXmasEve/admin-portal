<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Facades\DB;

final class BulkRegisterCourseOfferingStudentsAction
{
    /** @param list<string> $studentCodes @return array{results:list<array{student_id:string,success:bool,message:string}>,success_count:int,failure_count:int} */
    public static function run(object $courseOffering, array $studentCodes, int $campusId): array
    {
        if (in_array($courseOffering->course_status, ['completed', 'cancelled'], true)) {
            throw new \RuntimeException('Cannot register students to a completed or cancelled course.');
        }

        $unit = app(CourseOfferingCatalogReader::class)->offeringUnit((int) $courseOffering->unit_id);
        $students = app(StudentReferenceReader::class);
        $results = [];
        $success = 0;

        DB::transaction(function () use (&$results, &$success, $courseOffering, $studentCodes, $campusId, $unit, $students): void {
            foreach (array_values(array_unique($studentCodes)) as $studentCode) {
                $result = ['student_id' => $studentCode, 'success' => false, 'message' => ''];
                $student = $students->findByStudentCode($studentCode, $campusId);
                if ($student === null) {
                    $result['message'] = 'Student not found';
                } elseif ($unit === null) {
                    $result['message'] = 'The course offering has an invalid unit.';
                } elseif ($unit->unit_type === 'egc' && $student->gcCurrentLevel !== $unit->level) {
                    $result['message'] = 'Student is not eligible for this EGC unit.';
                } elseif ($student->status !== null && ! in_array($student->status, $unit->unit_type === 'egc' ? ['intake_pre_uni_gc'] : ['intake_course'], true)) {
                    $result['message'] = 'Student is not eligible for this unit.';
                } else {
                    try {
                        EnrollStudentInCourseOfferingAction::run([
                            'student_id' => $student->id,
                            'course_offering_id' => (int) $courseOffering->id,
                            'registration_status' => 'confirmed',
                            'registration_method' => 'admin_override',
                        ]);
                        $result['success'] = true;
                        $result['message'] = 'Student registered successfully';
                        $success++;
                    } catch (\Throwable $exception) {
                        $result['message'] = $exception->getMessage();
                    }
                }
                $results[] = $result;
            }
        });

        return ['results' => $results, 'success_count' => $success, 'failure_count' => count($results) - $success];
    }
}
