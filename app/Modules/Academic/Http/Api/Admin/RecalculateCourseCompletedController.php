<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecalculateCourseCompletedController extends Controller
{
    /**
     * Recalculate a completed course offering to update student results.
     */
    public function __invoke(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        try {
            // Only allow recalculating completed courses
            if ($courseOffering->course_status !== 'completed') {
                return ApiResponse::error('Course must be completed before recalculating.', [], 400);
            }

            $result = MarkCourseOfferingCompletedAction::run($courseOffering, recalculate: true);

            $message = $this->formatSuccessMessage($result);

            return ApiResponse::success([
                'success' => true,
                'message' => $message,
            ], $result);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), [], 400);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to recalculate course: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Format the success message with detailed results.
     */
    private function formatSuccessMessage(array $result): string
    {
        $message = "Course '{$result['course_code']}' recalculated successfully.";

        if (isset($result['egc_progression']) && $result['egc_progression']['processed']) {
            // EGC course message with detailed breakdown
            $egc = $result['egc_progression'];
            $message .= "<br><br><strong>EGC Summary:</strong> {$egc['total_students']} student(s) processed";

            // Show progressed students
            if (count($egc['progressed']) > 0) {
                $message .= '<br>✅ <strong>'.count($egc['progressed']).' Progressed:</strong>';
                foreach (array_slice($egc['progressed'], 0, 5) as $prog) {
                    $message .= "<br>&nbsp;&nbsp;• {$prog['student_id']} ({$prog['student_name']}): Level {$prog['from_level']} → {$prog['to_level']}";
                }
                if (count($egc['progressed']) > 5) {
                    $message .= '<br>&nbsp;&nbsp;• +'.(count($egc['progressed']) - 5).' more...';
                }
            }

            // Show failed students with reasons
            if (count($egc['failed_students']) > 0) {
                $message .= '<br>❌ <strong>'.count($egc['failed_students']).' Failed:</strong>';
                foreach (array_slice($egc['failed_students'], 0, 5) as $fail) {
                    $reason = $fail['action'];
                    $message .= "<br>&nbsp;&nbsp;• {$fail['student_id']} ({$fail['student_name']}): Grade {$fail['grade']} - {$reason}";
                }
                if (count($egc['failed_students']) > 5) {
                    $message .= '<br>&nbsp;&nbsp;• +'.(count($egc['failed_students']) - 5).' more...';
                }
            }

            // Show level mismatch warnings
            if (count($egc['warnings']) > 0) {
                $message .= '<br>⚠️ <strong>'.count($egc['warnings']).' Warnings:</strong>';
                foreach (array_slice($egc['warnings'], 0, 5) as $warn) {
                    $studentLevel = $warn['student_level'] ?? 'N/A';
                    $unitLevel = $warn['unit_level'] ?? 'N/A';

                    // Check if this is a level mismatch warning or status warning
                    if (isset($warn['reason']) && strpos($warn['reason'], 'Level mismatch') !== false) {
                        $message .= "<br>&nbsp;&nbsp;• {$warn['student_id']} ({$warn['student_name']}): Student at Level {$studentLevel}, passed Level {$unitLevel} course - Grade recorded but NOT progressed";
                    } elseif (isset($warn['reason'])) {
                        $message .= "<br>&nbsp;&nbsp;• {$warn['student_id']} ({$warn['student_name']}): {$warn['reason']}";
                    } else {
                        $message .= "<br>&nbsp;&nbsp;• {$warn['student_id']} ({$warn['student_name']}): ".($warn['reason'] ?? 'Warning');
                    }
                }
                if (count($egc['warnings']) > 5) {
                    $message .= '<br>&nbsp;&nbsp;• +'.(count($egc['warnings']) - 5).' more...';
                }
            }
        } elseif (isset($result['non_egc_result']) && $result['non_egc_result']) {
            // Non-EGC course message
            $nonEgc = $result['non_egc_result'];
            $totalStudents = $nonEgc['passed'] + $nonEgc['failed'];
            $message .= "<br><br><strong>Summary:</strong> {$totalStudents} student(s) processed";

            if ($nonEgc['passed'] > 0) {
                $message .= "<br>✅ {$nonEgc['passed']} passed";
            }

            if ($nonEgc['failed'] > 0) {
                $message .= "<br>❌ {$nonEgc['failed']} failed";
            }

            if (isset($nonEgc['skipped']) && $nonEgc['skipped'] > 0) {
                $message .= "<br>⏭️ {$nonEgc['skipped']} skipped (no status change)";
            }

            if (isset($nonEgc['total_notified']) && $nonEgc['total_notified'] > 0) {
                $message .= "<br>📧 {$nonEgc['total_notified']} notification(s) sent";
            }
        }

        return $message;
    }
}
