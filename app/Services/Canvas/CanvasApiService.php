<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Models\CanvasIntegration;
use Illuminate\Support\Facades\Log;

class CanvasApiService
{
    private CanvasHttpClient $client;

    private CanvasTokenService $tokenService;

    public function __construct(CanvasTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Initialize client for a specific integration
     */
    private function initClient(CanvasIntegration $integration): void
    {
        $this->client = new CanvasHttpClient($integration, $this->tokenService);
    }

    /**
     * Set integration for subsequent API calls (alias for initClient)
     */
    public function setIntegration(CanvasIntegration $integration): self
    {
        $this->initClient($integration);

        return $this;
    }

    /**
     * Get the OAuth authorization URL
     */
    public function getAuthorizationUrl(CanvasIntegration $integration, string $redirectUri, string $state): string
    {
        $params = [
            'client_id' => $integration->client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        // Only add scope if configured
        $scope = config('services.canvas.default_scopes', '');
        if (! empty($scope)) {
            $params['scope'] = $scope;
        }

        return rtrim($integration->canvas_url, '/').'/login/oauth2/auth?'.http_build_query($params);
    }

    /**
     * Get all courses from Canvas
     */
    public function getCourses(CanvasIntegration $integration, array $filters = []): array
    {
        $this->initClient($integration);

        try {
            $params = [
                'include[]' => 'term',
            ];

            // Add optional filters if provided
            if (! empty($filters['enrollment_type'])) {
                $params['enrollment_type'] = $filters['enrollment_type'];
            }
            if (! empty($filters['enrollment_state'])) {
                $params['enrollment_state'] = $filters['enrollment_state'];
            }
            if (isset($filters['published'])) {
                $params['published'] = $filters['published'];
            }

            // Add state filter to exclude deleted courses
            $params['state[]'] = 'available';

            // Try account-level endpoint first (requires admin access)
            // Falls back to user courses if account endpoint fails
            $endpoint = 'api/v1/accounts/1/courses'; // Account ID 1 is usually root account

            Log::info('Fetching courses from Canvas', [
                'integration_id' => $integration->id,
                'endpoint' => $endpoint,
                'params' => $params,
            ]);

            try {
                $courses = $this->client->getPaginated($endpoint, $params);
                Log::info('Retrieved courses from Canvas (account-level)', [
                    'integration_id' => $integration->id,
                    'count' => count($courses),
                ]);
            } catch (\Exception $e) {
                // Fallback to user courses
                Log::warning('Account-level courses failed, falling back to user courses', [
                    'integration_id' => $integration->id,
                    'error' => $e->getMessage(),
                ]);
                $courses = $this->client->getPaginated('api/v1/courses', $params);
                Log::info('Retrieved courses from Canvas (user-level)', [
                    'integration_id' => $integration->id,
                    'count' => count($courses),
                ]);
            }

            if (count($courses) > 0) {
                Log::info('Sample course data', [
                    'course' => $courses[0],
                ]);
            }

            return $courses;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve courses from Canvas', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get a specific course from Canvas
     */
    public function getCourse(CanvasIntegration $integration, string $courseId): ?array
    {
        $this->initClient($integration);

        try {
            $course = $this->client->get("api/v1/courses/{$courseId}", [
                'include' => ['term', 'total_students', 'teachers'],
            ]);

            Log::info('Retrieved course from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
            ]);

            return $course;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve course from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get course assignments
     */
    public function getCourseAssignments(CanvasIntegration $integration, string $courseId, array $params = []): array
    {
        $this->initClient($integration);

        try {
            return $this->client->getPaginated("api/v1/courses/{$courseId}/assignments", $params);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve course assignments from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get assignment groups with optional assignments
     */
    public function getAssignmentGroups(CanvasIntegration $integration, string $courseId, array $params = []): array
    {
        $this->initClient($integration);

        try {
            return $this->client->getPaginated("api/v1/courses/{$courseId}/assignment_groups", $params);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve assignment groups from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get students enrolled in a course
     */
    public function getCourseStudents(CanvasIntegration $integration, string $courseId): array
    {
        $this->initClient($integration);

        try {
            return $this->client->getPaginated("api/v1/courses/{$courseId}/users", [
                'enrollment_type[]' => 'student',
                'include[]' => 'email',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve students from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get student enrollment with grades
     */
    public function getStudentEnrollment(CanvasIntegration $integration, string $courseId, string $userId): ?array
    {
        $this->initClient($integration);

        try {
            $enrollments = $this->client->get("api/v1/courses/{$courseId}/enrollments", [
                'user_id' => $userId,
                'type[]' => 'StudentEnrollment',
                'include[]' => 'current_grading_period_scores',
            ]);

            return $enrollments[0] ?? null;
        } catch (\Exception $e) {
            Log::error('Failed to get student enrollment from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get submission for a specific student and assignment
     */
    public function getSubmission(CanvasIntegration $integration, string $courseId, string $assignmentId, string $canvasUserId): ?array
    {
        $this->initClient($integration);

        try {
            return $this->client->get("api/v1/courses/{$courseId}/assignments/{$assignmentId}/submissions/{$canvasUserId}");
        } catch (\Exception $e) {
            Log::error('Failed to retrieve submission from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'assignment_id' => $assignmentId,
                'canvas_user_id' => $canvasUserId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get all student submissions for specific assignments in a course
     * Fetches submissions for each assignment separately (Canvas API limitation)
     *
     * @param  array  $assignmentIds  Array of Canvas assignment IDs to fetch submissions for
     */
    public function getAllStudentSubmissions(CanvasIntegration $integration, string $courseId, array $assignmentIds = []): array
    {
        $this->initClient($integration);

        try {
            Log::info('Fetching submissions per assignment', [
                'course_id' => $courseId,
                'assignment_ids_count' => count($assignmentIds),
            ]);

            if (empty($assignmentIds)) {
                Log::warning('No assignment IDs provided - returning empty array');

                return [];
            }

            $startTime = microtime(true);
            $allSubmissions = [];

            // Fetch submissions for each assignment
            // Canvas API: GET /api/v1/courses/:course_id/assignments/:assignment_id/submissions
            foreach ($assignmentIds as $index => $assignmentId) {
                $assignmentStartTime = microtime(true);

                Log::info('Fetching submissions for assignment', [
                    'assignment_id' => $assignmentId,
                    'progress' => ($index + 1).'/'.count($assignmentIds),
                ]);

                try {
                    $submissions = $this->client->getPaginated(
                        "api/v1/courses/{$courseId}/assignments/{$assignmentId}/submissions",
                        []
                    );

                    $assignmentDuration = microtime(true) - $assignmentStartTime;

                    Log::info('Fetched assignment submissions', [
                        'assignment_id' => $assignmentId,
                        'submissions_count' => count($submissions),
                        'duration_seconds' => round($assignmentDuration, 2),
                    ]);

                    // Merge into all submissions
                    $allSubmissions = array_merge($allSubmissions, $submissions);

                } catch (\Exception $e) {
                    Log::error('Failed to fetch submissions for assignment', [
                        'assignment_id' => $assignmentId,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other assignments
                }
            }

            $duration = microtime(true) - $startTime;

            Log::info('Fetched all student submissions from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'assignment_ids_requested' => count($assignmentIds),
                'total_submissions' => count($allSubmissions),
                'api_call_duration_seconds' => round($duration, 2),
            ]);

            // Log detailed sample of submissions
            if (! empty($allSubmissions)) {
                Log::info('Submissions sample (first 3)', [
                    'sample_data' => array_slice($allSubmissions, 0, 3),
                ]);

                // Group by user to show distribution
                $userSubmissionCounts = [];
                foreach ($allSubmissions as $sub) {
                    $userId = $sub['user_id'] ?? 'unknown';
                    $userSubmissionCounts[$userId] = ($userSubmissionCounts[$userId] ?? 0) + 1;
                }
                Log::info('Submissions distribution by user', [
                    'unique_users' => count($userSubmissionCounts),
                    'user_ids_sample' => array_slice(array_keys($userSubmissionCounts), 0, 10),
                    'counts_sample' => array_slice($userSubmissionCounts, 0, 10),
                ]);
            }

            return $allSubmissions;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve bulk submissions from Canvas', [
                'integration_id' => $integration->id,
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            return [];
        }
    }

    /**
     * Test the connection to Canvas API
     */
    public function testConnection(CanvasIntegration $integration): bool
    {
        $this->initClient($integration);

        try {
            $this->client->get('api/v1/courses', ['per_page' => 1]);

            return true;
        } catch (\Exception $e) {
            Log::error('Canvas API connection test failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
