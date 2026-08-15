<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GoldTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Merchandise\Models\GoldTransaction;
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
     * Get a specific transaction. Student-owned only: the actor resolved by
     * the request (which is already the target Student even for a parent
     * proxy token, via ParentStudentAccess's user resolver override) must
     * own this transaction. `Auth::guard('student')->user()` is deliberately
     * NOT used here — it only resolves for a token authenticated directly on
     * the `student` guard, so a parent-proxy or any other non-student actor
     * made it null and skipped the ownership check entirely, letting anyone
     * read an arbitrary transaction by id. There is no staff/admin route to
     * this action (see routes/api/v1/student.php), so denying every
     * non-owning actor is correct, not just a stopgap.
     */
    public function show(Request $request, GoldTransaction $transaction): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Student || (int) $transaction->student_id !== (int) $actor->id) {
            return ApiResponse::authorizationError('Unauthorized to view this transaction');
        }

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
