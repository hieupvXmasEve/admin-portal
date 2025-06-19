<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    /**
     * Search students with pagination
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
            'status' => 'nullable|string|in:active,inactive,suspended',
        ]);

        $query = Student::query()
            ->with(['campus', 'program'])
            ->select([
                'id',
                'student_id',
                'full_name',
                'email',
                'campus_id',
                'program_id',
                'status'
            ]);

        // Apply status filter (default to active students)
        $status = $request->get('status', 'active');
        $query->where('status', $status);

        // Apply search filter
        if ($request->filled('query')) {
            $searchTerm = $request->query;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('student_id', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        // Order results
        $query->orderBy('full_name');

        // Paginate results
        $limit = $request->get('limit', 20);
        $students = $query->paginate($limit);

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully',
            'data' => [
                'items' => $students->items(),
                'pagination' => [
                    'current_page' => $students->currentPage(),
                    'per_page' => $students->perPage(),
                    'total' => $students->total(),
                    'last_page' => $students->lastPage(),
                    'has_more_pages' => $students->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Get student by ID
     */
    public function show(Student $student): JsonResponse
    {
        $student->load(['campus', 'program', 'specialization']);

        return response()->json([
            'success' => true,
            'message' => 'Student retrieved successfully',
            'data' => $student,
        ]);
    }

    /**
     * Get students by IDs
     */
    public function getByIds(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:students,id',
        ]);

        $students = Student::with(['campus', 'program'])
            ->whereIn('id', $request->ids)
            ->select([
                'id',
                'student_id',
                'full_name',
                'email',
                'campus_id',
                'program_id',
                'status'
            ])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully',
            'data' => $students,
        ]);
    }
}
