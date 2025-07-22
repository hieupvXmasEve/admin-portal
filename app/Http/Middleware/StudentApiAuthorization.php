<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StudentApiAuthorization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        // Check if user is authenticated via Sanctum
        if (!$request->user()) {
            return ApiResponse::authenticationError('Authentication required');
        }

        // Check if the authenticated user is a Student
        if (!$request->user() instanceof Student) {
            return ApiResponse::authorizationError('Student account required');
        }

        $student = $request->user();

        // Check if student account is active
        if (!$this->isStudentActive($student)) {
            return ApiResponse::authorizationError(
                'Student account is not active. Please contact administration.'
            );
        }

        // Check for academic holds that prevent access
        if ($this->hasBlockingHolds($student, $request)) {
            return ApiResponse::authorizationError(
                'Access restricted due to academic holds. Please resolve outstanding issues.'
            );
        }

        // Check specific permissions if provided
        if (!empty($permissions) && !$this->hasPermissions($student, $permissions)) {
            return ApiResponse::authorizationError(
                'Insufficient permissions for this action'
            );
        }

        // Add student context to request
        $request->attributes->set('student', $student);

        return $next($request);
    }

    /**
     * Check if student account is active
     */
    protected function isStudentActive(Student $student): bool
    {
        return in_array($student->status, ['active', 'enrolled']);
    }

    /**
     * Check if student has blocking academic holds
     */
    protected function hasBlockingHolds(Student $student, Request $request): bool
    {
        // Get route name to determine what holds block access
        $routeName = $request->route()?->getName() ?? '';
        
        $blockingHolds = $student->academicHolds()
            ->where('status', 'active')
            ->get();

        foreach ($blockingHolds as $hold) {
            if ($this->holdBlocksRoute($hold, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a hold blocks access to a specific route
     */
    protected function holdBlocksRoute($hold, string $routeName): bool
    {
        // Registration holds block course registration
        if ($hold->hold_category === 'registration' && 
            str_contains($routeName, 'registration')) {
            return true;
        }

        // Academic holds block grade access
        if ($hold->hold_category === 'academic' && 
            str_contains($routeName, 'grade')) {
            return true;
        }

        // Financial holds block most services
        if ($hold->hold_type === 'financial' && 
            !str_contains($routeName, 'profile')) {
            return true;
        }

        // All category holds block everything except profile
        if ($hold->hold_category === 'all' && 
            !str_contains($routeName, 'profile')) {
            return true;
        }

        return false;
    }

    /**
     * Check if student has required permissions
     */
    protected function hasPermissions(Student $student, array $permissions): bool
    {
        // For now, all active students have basic permissions
        // This can be extended with role-based permissions later
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($student, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if student has a specific permission
     */
    protected function hasPermission(Student $student, string $permission): bool
    {
        // Basic permissions for all active students
        $basicPermissions = [
            'view-dashboard',
            'view-profile',
            'update-profile',
            'view-grades',
            'view-attendance',
            'view-timetable',
            'register-courses',
            'view-academic-history',
        ];

        return in_array($permission, $basicPermissions);
    }
}
