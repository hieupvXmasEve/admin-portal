<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\BulkAssignInstructorsAction;
use App\Modules\Academic\Delivery\Actions\BulkUpdateCourseRegistrationStatusAction;
use App\Modules\Academic\Delivery\Actions\MoveStudentBetweenCourseOfferingSectionsAction;
use App\Modules\Academic\Delivery\Actions\RemoveCourseOfferingRosterMemberAction;
use App\Modules\Academic\Delivery\Exceptions\InstructorAssignmentException;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingRosterMemberQuery;
use App\Modules\Academic\Http\Requests\CourseDelivery\BulkAssignInstructorsRequest;
use App\Modules\Academic\Http\Requests\CourseDelivery\BulkUpdateCourseRegistrationStatusRequest;
use App\Modules\Academic\Http\Requests\CourseDelivery\RemoveCourseOfferingRosterMemberRequest;
use App\Modules\Academic\Http\Requests\MoveStudentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class CourseOfferingRosterController extends Controller
{
    public function moveStudent(MoveStudentRequest $request, CourseOffering $courseOffering): JsonResponse
    {
        $this->assertCurrentCampus($courseOffering);

        try {
            MoveStudentBetweenCourseOfferingSectionsAction::run($request->validated());

            return ApiResponse::success(null, [], 'Student moved successfully.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Failed to move course offering roster member.', [
                'course_offering_id' => $courseOffering->id,
                'exception' => $exception,
            ]);

            return ApiResponse::error('Failed to move student: '.$exception->getMessage(), [], 500);
        }
    }

    public function removeStudent(
        RemoveCourseOfferingRosterMemberRequest $request,
        CourseOffering $courseOffering,
        GetCourseOfferingRosterMemberQuery $query,
    ): JsonResponse {
        $this->assertCurrentCampus($courseOffering);

        $rosterMember = $query->handle($courseOffering, $request->integer('registration_id'));
        if ($rosterMember === null) {
            return ApiResponse::error('Registration not found or does not belong to this course offering.', [], 404);
        }

        try {
            RemoveCourseOfferingRosterMemberAction::run([
                'course_registration_id' => $rosterMember['registration_id'],
            ]);

            return ApiResponse::success([
                'deleted_registration_id' => $rosterMember['registration_id'],
                'student_name' => $rosterMember['student_name'],
                'student_id' => $rosterMember['student_code'],
            ], [], "Successfully removed {$rosterMember['student_name']} ({$rosterMember['student_code']}) from the course.");
        } catch (\Throwable $exception) {
            Log::error('Failed to remove course offering roster member.', [
                'course_offering_id' => $courseOffering->id,
                'course_registration_id' => $rosterMember['registration_id'],
                'exception' => $exception,
            ]);

            return ApiResponse::error('Failed to remove student from course: '.$exception->getMessage(), [], 500);
        }
    }

    public function bulkUpdateStatus(
        BulkUpdateCourseRegistrationStatusRequest $request,
        CourseOffering $courseOffering,
    ): JsonResponse {
        $this->assertCurrentCampus($courseOffering);
        $attributes = $request->validated();

        try {
            $updatedCount = BulkUpdateCourseRegistrationStatusAction::run([
                'course_offering_id' => (int) $courseOffering->id,
                'from_status' => $attributes['from_status'],
                'to_status' => $attributes['to_status'],
                'student_ids' => array_map('intval', $attributes['student_ids']),
            ]);

            return ApiResponse::success([
                'updated_count' => $updatedCount,
                'from_status' => $attributes['from_status'],
                'to_status' => $attributes['to_status'],
            ], [], "Successfully updated {$updatedCount} student registration(s) from {$attributes['from_status']} to {$attributes['to_status']}.");
        } catch (\Throwable $exception) {
            Log::error('Failed to update course offering roster status.', [
                'course_offering_id' => $courseOffering->id,
                'exception' => $exception,
            ]);

            return ApiResponse::error('Failed to update registration status: '.$exception->getMessage(), [], 500);
        }
    }

    public function bulkAssignInstructors(BulkAssignInstructorsRequest $request): RedirectResponse
    {
        try {
            $assignmentsCount = BulkAssignInstructorsAction::run([
                'campus_id' => (int) app('campus')->id,
                'assignments' => $request->validated('assignments'),
            ]);

            Inertia::flash('success', "Successfully assigned lectures to {$assignmentsCount} course offerings.");

            return Redirect::back();
        } catch (InstructorAssignmentException $exception) {
            throw ValidationException::withMessages([
                $exception->field => [$exception->getMessage()],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Failed to assign course offering instructors.', ['exception' => $exception]);
            Inertia::flash('error', 'Failed to assign lectures: '.$exception->getMessage());

            return Redirect::back();
        }
    }

    private function assertCurrentCampus(CourseOffering $courseOffering): void
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }
    }
}
