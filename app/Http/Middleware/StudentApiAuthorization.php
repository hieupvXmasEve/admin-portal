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
        if (! $request->user()) {
            return ApiResponse::authenticationError('Authentication required');
        }

        // Check if the authenticated user is a Student
        if (! $request->user() instanceof Student) {
            return ApiResponse::authorizationError('Student account required');
        }

        $student = $request->user();

        // Check if student account is active
        if (! $student->isActive()) {
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
        if (! empty($permissions) && ! $this->hasPermissions($student, $permissions)) {
            return ApiResponse::authorizationError(
                'Insufficient permissions for this action'
            );
        }

        // Add student context to request
        $request->attributes->set('student', $student);

        return $next($request);
    }

    /**
     * Check if student has blocking academic holds
     */
    protected function hasBlockingHolds(Student $student, Request $request): bool
    {
        // Get route name to determine what holds block access
        $routeName = $request->route()?->getName() ?? '';

        $blockingHolds = $student->academicHolds()
            ->active()
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
        if (
            $hold->hold_category === 'registration' &&
            str_contains($routeName, 'registration')
        ) {
            return true;
        }

        // Academic holds block grade access
        if (
            $hold->hold_category === 'academic' &&
            str_contains($routeName, 'grade')
        ) {
            return true;
        }

        // Financial holds block most services
        if (
            $hold->hold_type === 'financial' &&
            ! $this->isHoldExemptRoute($routeName)
        ) {
            return true;
        }

        // All category holds block everything except the exempt allow-list
        if (
            $hold->hold_category === 'all' &&
            ! $this->isHoldExemptRoute($routeName)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Route-name allow-list exempt from financial/all holds. `profile` is the
     * long-standing exemption; scholarship-adjustment confirmation is added so
     * the fee-increase cohort (who by definition carry financial exposure) can
     * still acknowledge the interview minutes instead of being locked out and
     * then counted as non-responsive. Match is a `str_contains` on the route
     * NAME by a distinctive token — a new route must not reuse these tokens in
     * its name unless it should also be hold-exempt.
     */
    private function isHoldExemptRoute(string $routeName): bool
    {
        $exemptTokens = [
            'profile',
            'scholarship-adjustment-confirmation',
        ];

        foreach ($exemptTokens as $token) {
            if (str_contains($routeName, $token)) {
                return true;
            }
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
            if (! $this->hasPermission($student, $permission)) {
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
