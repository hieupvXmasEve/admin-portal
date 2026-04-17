<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailConfiguration;
use App\Services\SmtpConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class EmailConfigurationController extends Controller
{
    public function __construct(
        protected SmtpConfigurationService $smtpService
    ) {
    }

    /**
     * Display a listing of email configurations.
     */
    public function index(): JsonResponse
    {
        $campusId = session('current_campus_id') ? (int) session('current_campus_id') : null;
        $configurations = $this->smtpService->getAll($campusId);
        $statistics = $this->smtpService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $configurations,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Store a newly created email configuration.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(EmailConfiguration::validationRules());

            // Default campus_id to current session campus if not explicitly set
            if (!array_key_exists('campus_id', $validated)) {
                $validated['campus_id'] = session('current_campus_id') ? (int) session('current_campus_id') : null;
            }

            $configuration = $this->smtpService->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Email configuration created successfully',
                'data' => $configuration,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified email configuration.
     */
    public function show(EmailConfiguration $configuration): JsonResponse
    {
        $this->authorizeCampusAccess($configuration);

        return response()->json([
            'success' => true,
            'data' => $configuration,
        ]);
    }

    /**
     * Update the specified email configuration.
     */
    public function update(Request $request, EmailConfiguration $configuration): JsonResponse
    {
        $this->authorizeCampusAccess($configuration);

        try {
            $rules = EmailConfiguration::validationRules();
            // Make password optional for updates
            $rules['password'] = 'nullable|string|max:255';

            $validated = $request->validate($rules);

            // Don't update password if not provided
            if (empty($validated['password'])) {
                unset($validated['password']);
            }

            $configuration = $this->smtpService->update($configuration, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Email configuration updated successfully',
                'data' => $configuration,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified email configuration.
     */
    public function destroy(EmailConfiguration $configuration): JsonResponse
    {
        $this->authorizeCampusAccess($configuration);

        try {
            $this->smtpService->delete($configuration);

            return response()->json([
                'success' => true,
                'message' => 'Email configuration deleted successfully',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test email configuration connection.
     */
    public function test(EmailConfiguration $configuration): JsonResponse
    {
        $this->authorizeCampusAccess($configuration);

        try {
            $result = $this->smtpService->testConnection($configuration);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 200 : 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test connection with configuration data (before saving).
     */
    public function testData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(EmailConfiguration::validationRules());

            $result = $this->smtpService->testConnectionWithData($validated);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ], $result['success'] ? 200 : 400);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test email configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set configuration as active.
     */
    public function setActive(EmailConfiguration $configuration): JsonResponse
    {
        $this->authorizeCampusAccess($configuration);

        try {
            $configuration->setAsActive();

            return response()->json([
                'success' => true,
                'message' => 'Email configuration set as active',
                'data' => $configuration->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set email configuration as active',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify the configuration belongs to the current campus session or is global.
     * Global (campus_id=null) configs are accessible from any campus context.
     */
    private function authorizeCampusAccess(EmailConfiguration $configuration): void
    {
        $campusId = session('current_campus_id') ? (int) session('current_campus_id') : null;

        if ($configuration->campus_id !== null && $configuration->campus_id !== $campusId) {
            abort(403, 'Access denied to this email configuration.');
        }
    }
}
