<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\StudentCashWalletResource;
use App\Http\Resources\Api\V1\Student\StudentInvoiceResource;
use App\Http\Resources\Api\V1\Student\TuitionPlanResource;
use App\Http\Resources\Api\V1\Student\WalletTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\StudentInvoice;
use App\Models\TuitionPlan;
use App\Services\CashWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CashWalletController extends Controller
{
    public function __construct(
        protected CashWalletService $walletService
    ) {}

    /**
     * Get wallet information for the authenticated student.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $student = $request->user();
            $wallet = $this->walletService->getOrCreateWallet($student->id);

            return ApiResponse::success(
                new StudentCashWalletResource($wallet),
                [],
                'Wallet information retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve wallet information', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve wallet information');
        }
    }

    /**
     * Get wallet summary including stats and tuition plan.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $student = $request->user();
            $wallet = $this->walletService->getOrCreateWallet($student->id);
            $stats = $this->walletService->getWalletStats($wallet->id);

            // Get tuition plan for the student
            $tuitionPlan = TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
                ->where('intake_semester_id', $student->intake_semester_id)
                ->where('is_active', true)
                ->with([
                    'curriculumVersion:id,version_code,program_id,specialization_id',
                    'curriculumVersion.program:id,name,code',
                    'curriculumVersion.specialization:id,name,code',
                    'intakeSemester:id,code,name,start_date,end_date',
                    'terms' => function ($query) {
                        $query->with('semester:id,code,name,start_date,end_date')
                            ->orderBy('term_number');
                    },
                ])
                ->first();

            return ApiResponse::success([
                'wallet' => new StudentCashWalletResource($wallet),
                'stats' => $stats,
                'tuition_plan' => $tuitionPlan ? new TuitionPlanResource($tuitionPlan) : null,
            ], [], 'Wallet summary retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to retrieve wallet summary', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve wallet summary');
        }
    }

    /**
     * Get tuition plan for the authenticated student.
     */
    public function tuitionPlan(Request $request): JsonResponse
    {
        try {
            $student = $request->user();

            $tuitionPlan = TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
                ->where('intake_semester_id', $student->intake_semester_id)
                ->where('is_active', true)
                ->with([
                    'curriculumVersion:id,version_code,program_id,specialization_id',
                    'curriculumVersion.program:id,name,code',
                    'curriculumVersion.specialization:id,name,code',
                    'intakeSemester:id,code,name,start_date,end_date',
                    'terms' => function ($query) {
                        $query->with('semester:id,code,name,start_date,end_date')
                            ->orderBy('term_number');
                    },
                ])
                ->first();

            if (! $tuitionPlan) {
                return ApiResponse::success(
                    null,
                    [],
                    'No tuition plan found for this student'
                );
            }

            return ApiResponse::success(
                new TuitionPlanResource($tuitionPlan),
                [],
                'Tuition plan retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve tuition plan', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve tuition plan');
        }
    }

    /**
     * Get transaction history with pagination.
     */
    public function transactions(Request $request): JsonResponse
    {
        try {
            $student = $request->user();
            $wallet = $this->walletService->getOrCreateWallet($student->id);

            $perPage = (int) $request->input('per_page', 20);
            $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100

            $transactions = $this->walletService->getTransactionHistory($wallet->id, $perPage);

            return ApiResponse::paginated(
                $transactions->setCollection(
                    $transactions->getCollection()->map(fn ($transaction) => new WalletTransactionResource($transaction))
                ),
                'Transaction history retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve transaction history', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve transaction history');
        }
    }

    /**
     * Get recent transactions.
     */
    public function recentTransactions(Request $request): JsonResponse
    {
        try {
            $student = $request->user();
            $wallet = $this->walletService->getOrCreateWallet($student->id);

            $limit = (int) $request->input('limit', 10);
            $limit = min(max($limit, 1), 50); // Limit between 1 and 50

            $transactions = $this->walletService->getRecentTransactions($wallet->id, $limit);

            return ApiResponse::success(
                WalletTransactionResource::collection($transactions),
                [],
                'Recent transactions retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve recent transactions', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve recent transactions');
        }
    }

    /**
     * Get wallet statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $student = $request->user();
            $wallet = $this->walletService->getOrCreateWallet($student->id);

            $stats = $this->walletService->getWalletStats($wallet->id);

            return ApiResponse::success(
                $stats,
                [],
                'Wallet statistics retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve wallet statistics', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve wallet statistics');
        }
    }

    /**
     * Get invoices for the authenticated student.
     */
    public function invoices(Request $request): JsonResponse
    {
        try {
            $student = $request->user();

            $query = StudentInvoice::where('student_id', $student->id)
                ->with(['semester:id,code,name', 'billingCycle:id,name,start_date,end_date', 'items']);

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by semester if provided
            if ($request->has('semester_id')) {
                $query->where('semester_id', $request->input('semester_id'));
            }

            // Order by due date descending (most recent first)
            $query->orderBy('due_date', 'desc');

            $perPage = (int) $request->input('per_page', 20);
            $perPage = min(max($perPage, 1), 100);

            $invoices = $query->paginate($perPage);

            return ApiResponse::paginated(
                $invoices->setCollection(
                    $invoices->getCollection()->map(fn ($invoice) => new StudentInvoiceResource($invoice))
                ),
                'Invoices retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve invoices', [
                'student_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve invoices');
        }
    }

    /**
     * Get invoice detail.
     */
    public function invoiceDetail(Request $request, StudentInvoice $invoice): JsonResponse
    {
        try {
            $student = $request->user();

            // Verify the invoice belongs to the authenticated student
            if ($invoice->student_id !== $student->id) {
                return ApiResponse::authorizationError('You are not authorized to view this invoice');
            }

            // Load relationships
            $invoice->load([
                'items',
                'discounts',
                'semester:id,code,name',
                'billingCycle:id,name,start_date,end_date',
            ]);

            return ApiResponse::success(
                new StudentInvoiceResource($invoice),
                [],
                'Invoice detail retrieved successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve invoice detail', [
                'student_id' => $request->user()?->id,
                'invoice_id' => $invoice->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to retrieve invoice detail');
        }
    }
}
