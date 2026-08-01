<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoldTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\GoldTransaction;
use App\Models\Student;
use App\Services\GoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GoldTransactionController extends Controller
{
    public function __construct(private GoldService $goldService)
    {
        // Middleware will be applied via routes
    }

    /**
     * Get transaction history for authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        $student = Auth::guard('student')->user();

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(GoldTransaction::TYPES)],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $transactions = $this->goldService->getTransactionHistory(
            $student,
            $validated['per_page'] ?? 15,
            $validated['type'] ?? null
        );

        // Transform the paginated results to use our resource
        $transactions->getCollection()->transform(function ($transaction) {
            return new GoldTransactionResource($transaction);
        });

        return ApiResponse::paginated(
            $transactions,
            'Transaction history retrieved successfully'
        );
    }

    /**
     * Get transaction history for a specific student (admin only).
     */
    public function indexForStudent(Student $student, Request $request): JsonResponse
    {

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(GoldTransaction::TYPES)],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $transactions = $this->goldService->getTransactionHistory(
            $student,
            $validated['per_page'] ?? 15,
            $validated['type'] ?? null
        );

        // Transform the paginated results to use our resource
        $transactions->getCollection()->transform(function ($transaction) {
            return new GoldTransactionResource($transaction);
        });

        return ApiResponse::paginated(
            $transactions,
            'Student transaction history retrieved successfully'
        );
    }

    /**
     * Get a specific transaction.
     */
    public function show(GoldTransaction $transaction): JsonResponse
    {
        $user = Auth::user();
        $student = Auth::guard('student')->user();

        // Students can only view their own transactions
        if ($student && $transaction->student_id !== $student->id) {
            return ApiResponse::authorizationError('Unauthorized to view this transaction');
        }

        // Staff can view any transaction (handled by policy)

        return ApiResponse::success(
            new GoldTransactionResource($transaction->load('student')),
            message: 'Transaction details retrieved successfully'
        );
    }

    /**
     * Get recent transactions for authenticated student.
     */
    public function recent(Request $request): JsonResponse
    {
        $student = Auth::guard('student')->user();

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $transactions = $this->goldService->getRecentTransactions(
            $student,
            $validated['limit'] ?? 10
        );

        return ApiResponse::success(
            GoldTransactionResource::collection($transactions),
            message: 'Recent transactions retrieved successfully'
        );
    }

    /**
     * Get recent transactions for a specific student (admin only).
     */
    public function recentForStudent(Student $student, Request $request): JsonResponse
    {

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $transactions = $this->goldService->getRecentTransactions(
            $student,
            $validated['limit'] ?? 10
        );

        return ApiResponse::success(
            GoldTransactionResource::collection($transactions),
            message: 'Student recent transactions retrieved successfully'
        );
    }

    /**
     * Get transaction statistics for authenticated student.
     */
    public function stats(): JsonResponse
    {
        $student = Auth::guard('student')->user();
        $stats = $this->goldService->getTransactionStats($student);

        return ApiResponse::success(
            $stats,
            message: 'Transaction statistics retrieved successfully'
        );
    }

    /**
     * Get transaction statistics for a specific student (admin only).
     */
    public function statsForStudent(Student $student): JsonResponse
    {

        $stats = $this->goldService->getTransactionStats($student);

        return ApiResponse::success(
            $stats,
            message: 'Student transaction statistics retrieved successfully'
        );
    }
}
