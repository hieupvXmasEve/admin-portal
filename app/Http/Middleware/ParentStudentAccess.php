<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\GuardianAccessGrantReader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ParentStudentAccess
{
    public function __construct(
        private readonly GuardianAccessGrantReader $accessGrantReader,
    ) {}

    /**
     * Handle an incoming request.
     *
     * This middleware allows parents to access student APIs by:
     * 1. If user is Student → pass through normally
     * 2. If user is User (parent) → require student_id, verify relationship, inject Student
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::authenticationError('Authentication required');
        }

        // If already a Student, pass through normally
        if ($user instanceof Student) {
            return $next($request);
        }

        // If User (parent), check for student access
        if ($user instanceof User) {
            return $this->handleParentAccess($request, $next, $user);
        }

        return ApiResponse::authorizationError('Invalid user type');
    }

    /**
     * Handle parent access to student APIs
     */
    protected function handleParentAccess(Request $request, Closure $next, User $parent): Response
    {
        // Get student_id from multiple possible sources
        $studentId = $request->input('student_id')
            ?? $request->header('X-Student-ID')
            ?? $request->route('student_id');

        if (! $studentId) {
            return ApiResponse::validationError([
                'student_id' => ['Parent must specify student_id to access student data'],
            ]);
        }

        // Find the student and verify parent relationship
        // Prioritize business student_id over internal ID
        $student = Student::where('student_id', $studentId)->first()
            ?? Student::where('id', $studentId)->first();

        if (! $student) {
            return ApiResponse::notFound('Student not found');
        }

        // Verify parent has access to this student
        if (! $this->canParentAccessStudent($parent, $student)) {
            return ApiResponse::authorizationError('You do not have permission to access this student\'s data');
        }

        // Check student account status
        if (! $this->isStudentAccessible($student)) {
            return ApiResponse::authorizationError('Student account is not accessible');
        }

        // Override the user resolver to return the Student instead of User
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        // Add parent context for logging/auditing
        $request->attributes->set('accessing_parent', $parent);
        $request->attributes->set('student_access_method', 'parent_proxy');

        return $next($request);
    }

    /**
     * Check if parent can access this student's data
     */
    protected function canParentAccessStudent(User $parent, Student $student): bool
    {
        return $this->accessGrantReader->hasActiveGrant((int) $parent->id, (int) $student->id);
    }

    /**
     * Check if student account is in a state that allows access
     */
    protected function isStudentAccessible(Student $student): bool
    {
        // Use the Model's logic for consistency
        if (! $student->isActive()) {
            return false;
        }

        // Specific allowed statuses for parents (may include 'pending' or 'intake' types)
        $allowedStatuses = [
            'active',
            'enrolled',
            'intake_pre_uni_gc',
            'intake_pre_uni',
            'intake',
            'pre_uni',
            'intake_course',
        ];

        if (! in_array($student->status, $allowedStatuses)) {
            return false;
        }

        return true;
    }

    /**
     * Extract student ID from various route patterns
     */
    protected function getStudentIdFromRoute(Request $request): ?string
    {
        $route = $request->route();

        if (! $route) {
            return null;
        }

        // Common route parameter names for student ID
        $possibleParams = ['student', 'student_id', 'studentId'];

        foreach ($possibleParams as $param) {
            if ($route->hasParameter($param)) {
                return $route->parameter($param);
            }
        }

        return null;
    }
}
