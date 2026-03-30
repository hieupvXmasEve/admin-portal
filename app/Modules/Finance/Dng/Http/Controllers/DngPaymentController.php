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
        $studentQuery = Student::query()->whereKey($validated['student_id']);
        $campus = app()->bound('campus') ? app('campus') : null;
        if ($campus !== null && isset($campus->id)) {
            $studentQuery->where('campus_id', (int) $campus->id);
        }

        $student = $studentQuery->firstOrFail();

        $chargeData = array_merge($validated, [
            'campus_code' => (string) config('services.dng.campus_code'),
            'student_code' => $student->student_id,
            'type' => (string) ($validated['type'] ?? $validated['fee_type']),
            'student_name' => $student->full_name,
            'email' => $student->email ?? '',
            'student_address' => $student->address ?? $student->current_address_line ?? 'N/A',
            'cccd' => $student->national_id ?? '',
            'fee_types' => $validated['fee_types'] ?? [$validated['fee_type']],
        ]);

        $dngRequest = $this->dngPaymentService->createAndPush($student, $chargeData);

        return response()->json([
            'data' => [
                'id' => $dngRequest->id,
                'status' => $dngRequest->status,
                'description' => $dngRequest->description,
                'dng_transaction_id' => $dngRequest->dng_transaction_id,
                'dng_payment_id' => $dngRequest->dng_payment_id,
            ],
        ], 201);
    }
}
