<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentApplicationRequest;
use App\Http\Requests\UpdateStudentApplicationRequest;
use App\Models\StudentApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentApplicationController extends Controller
{
    /**
     * Display a listing of student applications
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentApplication::query();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campus_code')) {
            $query->where('campus_code', $request->campus_code);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%")
                    ->orWhere('student_code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $applications = $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $applications,
            'filters' => $request->only(['status', 'campus_code', 'search', 'per_page']),
        ]);
    }

    /**
     * Display the specified student application
     */
    public function show(StudentApplication $studentApplication): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $studentApplication,
        ]);
    }

    /**
     * Store newly created student applications
     */
    public function store(StoreStudentApplicationRequest $request): JsonResponse
    {
        $students = $request->validated()['students'];

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($students, &$created, &$updated, &$errors) {
            foreach ($students as $index => $studentData) {
                try {
                    $existingStudent = StudentApplication::where('email', $studentData['email'])
                        ->orWhere(function ($query) use ($studentData) {
                            if (! empty($studentData['national_id'])) {
                                $query->where('national_id', $studentData['national_id']);
                            }
                        })
                        ->first();

                    if ($existingStudent) {
                        $existingStudent->update($studentData);
                        $updated++;
                    } else {
                        StudentApplication::create($studentData);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'email' => $studentData['email'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Student applications processed successfully',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
                'total_processed' => count($students),
                'total_failed' => count($errors),
            ],
        ]);
    }

    /**
     * Update the status of a student application
     */
    public function updateStatus(UpdateStudentApplicationRequest $request, StudentApplication $studentApplication): JsonResponse
    {
        try {
            $studentApplication->update([
                'status' => $request->validated('status'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Application status updated successfully',
                'data' => $studentApplication->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update application status: '.$e->getMessage(),
            ], 500);
        }
    }
}
