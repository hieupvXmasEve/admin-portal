<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Modules\Finance\Dng\Http\Requests\CreateDngPaymentFormRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use Illuminate\Http\JsonResponse;

class DngPaymentController extends Controller
{
    public function __construct(
        protected DngPaymentService $dngPaymentService,
    ) {}

    /**
     * Create a DNG payment request and push debt to DNG.
     */
    public function store(CreateDngPaymentFormRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $student = Student::findOrFail($validated['student_id']);

        $dngRequest = $this->dngPaymentService->createAndPush($student, $validated);

        // Optionally pull QR if fee_types provided
        $qrData = null;
        if (! empty($validated['fee_types'])) {
            $qrData = $this->dngPaymentService->pullQrCode(
                $dngRequest,
                $validated['fee_types'],
            );
        }

        return response()->json([
            'data' => [
                'id' => $dngRequest->id,
                'status' => $dngRequest->status,
                'dng_transaction_id' => $dngRequest->dng_transaction_id,
                'dng_payment_id' => $dngRequest->dng_payment_id,
                'qr' => $qrData,
            ],
        ], 201);
    }

    /**
     * Pull a fresh QR code for an existing DNG payment request.
     */
    public function qr(DngPaymentRequest $dngPaymentRequest): JsonResponse
    {
        $qrData = $this->dngPaymentService->pullQrCode(
            $dngPaymentRequest,
            [$dngPaymentRequest->fee_type],
        );

        return response()->json([
            'data' => $qrData,
        ]);
    }
}
