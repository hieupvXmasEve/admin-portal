<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Lecture;
use App\Models\User;
use App\Shared\Contracts\Identity\LecturerAccessGrantReader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LecturerApiAuthorization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        // Check if user is authenticated via Sanctum
        if (! $request->user()) {
            return ApiResponse::authenticationError('Authentication required');
        }

        // Check if the authenticated user is a Lecturer
        if (! $request->user() instanceof Lecture) {
            return ApiResponse::authorizationError('Lecturer account required');
        }

        $lecturer = $request->user();

        // Access state is owned by Identity. Workforce remains responsible for
        // updating the grant synchronously, not for being queried at request time.
        if (! $this->hasActiveIdentityAccess($lecturer)) {
            return ApiResponse::authorizationError(
                'Lecturer account is not active. Please contact administration.'
            );
        }

        // Check specific permissions if provided
        if (! empty($permissions) && ! $this->hasPermissions($lecturer, $permissions)) {
            return ApiResponse::authorizationError(
                'Insufficient permissions for this action'
            );
        }

        // Add lecturer context to request
        $request->attributes->set('lecturer', $lecturer);

        return $next($request);
    }

    /**
     * Check if lecturer account is active
     */
    protected function hasActiveIdentityAccess(Lecture $lecturer): bool
    {
        $user = User::query()->find($lecturer->user_id);
        if ($user === null || ! $user->isActive()) {
            return false;
        }

        return app(LecturerAccessGrantReader::class)->activeForUser((int) $user->id) !== null;
    }

    /**
     * Check if lecturer has all required permissions
     */
    protected function hasPermissions(Lecture $lecturer, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($lecturer, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if lecturer has a specific permission
     */
    protected function hasPermission(Lecture $lecturer, string $permission): bool
    {
        // Basic permissions for all active lecturers
        $basicPermissions = [
            'view-dashboard',
            'view-profile',
            'update-profile',
            'view-courses',
            'view-students',
            'view-attendance',
            'mark-attendance',
            'view-timetable',
            'manage-sessions',
            'view-reports',
            'manage-student-notes',
        ];

        // Additional permissions based on academic rank
        $seniorPermissions = [
            'manage-course-materials',
            'update-syllabus',
            'generate-reports',
            'manage-course-settings',
        ];

        // Administrative permissions for senior staff
        $adminPermissions = [
            'view-all-courses',
            'manage-lecturer-assignments',
            'access-admin-reports',
        ];

        // Check basic permissions
        if (in_array($permission, $basicPermissions)) {
            return true;
        }

        // Check senior permissions for senior lecturers and above
        if (in_array($permission, $seniorPermissions)) {
            return in_array($lecturer->academic_rank, [
                'senior_lecturer',
                'associate_professor',
                'professor',
                'emeritus_professor',
            ]);
        }

        // Check admin permissions for professors and above
        if (in_array($permission, $adminPermissions)) {
            return in_array($lecturer->academic_rank, [
                'associate_professor',
                'professor',
                'emeritus_professor',
            ]);
        }

        return false;
    }

    /**
     * Check if lecturer can access specific course
     */
    protected function canAccessCourse(Lecture $lecturer, int $courseOfferingId): bool
    {
        return $lecturer->courseOfferings()
            ->where('id', $courseOfferingId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check if lecturer can access specific student data
     */
    protected function canAccessStudent(Lecture $lecturer, int $studentId): bool
    {
        // Lecturer can access student data if they teach any course the student is enrolled in
        return $lecturer->courseOfferings()
            ->whereHas('courseRegistrations', function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->where('registration_status', 'enrolled');
            })
            ->exists();
    }
}
