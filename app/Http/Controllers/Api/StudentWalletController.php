<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustWalletBalanceRequest;
use App\Http\Resources\StudentWalletResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentWalletController extends Controller
{
    public function __construct(private WalletService $walletService)
    {
        // Middleware will be applied via routes
    }

    /**
     * Get wallet for the authenticated student.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($student);

        return ApiResponse::success(
            new StudentWalletResource($wallet->load('student')),
            message: 'Wallet information retrieved successfully'
        );
    }

    /**
     * Get wallet summary with stats and recent transactions.
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var \App\Models\Student $student */
        $student = $request->user();
        $summary = $this->walletService->getWalletSummary($student);

        return ApiResponse::success(
            [
                'wallet' => new StudentWalletResource($summary['wallet']),
                'stats' => $summary['stats'],
                'recent_transactions' => WalletTransactionResource::collection($summary['recent_transactions']),
            ],
            message: 'Wallet summary retrieved successfully'
        );
    }

    /**
     * Get wallet for a specific student (admin only).
     */
    public function showStudent(Student $student): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($student);

        return ApiResponse::success(
            new StudentWalletResource($wallet->load('student')),
            message: 'Student wallet information retrieved successfully'
        );
    }

    /**
     * Get wallet summary for a specific student (admin only).
     */
    public function summaryForStudent(Student $student): JsonResponse
    {
        $summary = $this->walletService->getWalletSummary($student);

        return ApiResponse::success(
            [
                'wallet' => new StudentWalletResource($summary['wallet']),
                'stats' => $summary['stats'],
                'recent_transactions' => WalletTransactionResource::collection($summary['recent_transactions']),
            ],
            message: 'Student wallet summary retrieved successfully'
        );
    }

    /**
     * Adjust student wallet balance (staff only).
     */
    public function adjustBalance(
        Student $student,
        AdjustWalletBalanceRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $user = Auth::user();

        try {
            $transaction = $this->walletService->adjustBalance(
                $student,
                $validated['amount'],
                $validated['notes'],
                $user->id
            );

            $wallet = $student->wallet->fresh();

            return ApiResponse::success(
                [
                    'transaction' => new WalletTransactionResource($transaction),
                    'wallet' => new StudentWalletResource($wallet),
                ],
                message: 'Wallet balance adjusted successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::businessLogicError(
                $e->getMessage(),
                [['code' => 'INSUFFICIENT_BALANCE', 'field' => 'amount', 'detail' => $e->getMessage()]]
            );
        }
    }

    /**
     * Check if student has sufficient balance.
     */
    public function checkBalance(Student $student, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $hasSufficient = $this->walletService->hasSufficientBalance(
            $student,
            $validated['amount']
        );

        $currentBalance = $this->walletService->getBalance($student);

        return ApiResponse::success(
            [
                'has_sufficient_balance' => $hasSufficient,
                'current_balance' => $currentBalance,
                'requested_amount' => number_format($validated['amount'], 2),
            ],
            message: 'Balance check completed successfully'
        );
    }
}
