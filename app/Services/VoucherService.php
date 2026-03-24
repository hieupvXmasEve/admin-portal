<?php

namespace App\Services;

use App\Models\Semester;
use App\Models\Student;
use App\Models\VoucherApplication;
use App\Models\VoucherDefinition;
use App\Models\VoucherRedemption;
use App\Modules\Finance\Support\VoucherDiscountAmountResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function __construct(
        private readonly VoucherDiscountAmountResolver $voucherDiscountAmountResolver
    ) {}

    /**
     * Create a new voucher definition
     */
    public function createVoucher(array $data): VoucherDefinition
    {
        $this->validateVoucherData($data);

        return VoucherDefinition::create($data);
    }

    /**
     * Update an existing voucher definition
     */
    public function updateVoucher(int $id, array $data): VoucherDefinition
    {
        $voucher = VoucherDefinition::findOrFail($id);

        $this->validateVoucherData($data, $voucher->id);

        $voucher->update($data);

        return $voucher->fresh();
    }

    /**
     * Delete a voucher definition
     */
    public function deleteVoucher(int $id): bool
    {
        $voucher = VoucherDefinition::findOrFail($id);

        if ($voucher->applications()->exists() || $voucher->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'voucher' => ['Cannot delete voucher that has been used by students.'],
            ]);
        }

        return $voucher->delete();
    }

    /**
     * Validate voucher data
     */
    protected function validateVoucherData(array $data, ?int $excludeId = null): void
    {
        // Check code uniqueness
        if (isset($data['code'])) {
            $query = VoucherDefinition::where('code', $data['code']);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'code' => ['The voucher code has already been taken.']
                ]);
            }
        }

        // Validate discount voucher has discount details
        if (isset($data['voucher_type']) && $data['voucher_type'] === 'discount') {
            if (empty($data['discount_type']) || !isset($data['discount_value'])) {
                throw ValidationException::withMessages([
                    'discount_type' => ['Discount vouchers must have a discount type and value.']
                ]);
            }

            if ($data['discount_value'] <= 0) {
                throw ValidationException::withMessages([
                    'discount_value' => ['Discount value must be greater than zero.']
                ]);
            }

            if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
                throw ValidationException::withMessages([
                    'discount_value' => ['Percentage discount cannot exceed 100%.']
                ]);
            }
        }

        // Validate date range
        if (isset($data['valid_from']) && isset($data['valid_until'])) {
            $validFrom = Carbon::parse($data['valid_from']);
            $validUntil = Carbon::parse($data['valid_until']);

            if ($validUntil->lessThan($validFrom)) {
                throw ValidationException::withMessages([
                    'valid_until' => ['Valid until date must be after valid from date.']
                ]);
            }
        }
    }

    /**
     * Validate if a voucher can be redeemed by a student
     */
    public function validateVoucher(VoucherDefinition $voucher, Student $student, Semester $semester): void
    {
        if (! $voucher->is_active) {
            throw ValidationException::withMessages([
                'voucher_id' => ['This voucher is not active.'],
            ]);
        }

        if (! $voucher->isCurrentlyValid()) {
            throw ValidationException::withMessages([
                'voucher_id' => ['This voucher is not valid at this time.'],
            ]);
        }

        $existingApplication = VoucherApplication::query()
            ->where('voucher_definition_id', $voucher->id)
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->exists();

        if ($existingApplication) {
            throw ValidationException::withMessages([
                'student_id' => ['This voucher has already been applied to the selected student for the active semester.'],
            ]);
        }

        if ($voucher->max_uses_per_student !== null) {
            $usageCount = VoucherApplication::query()
                ->where('voucher_definition_id', $voucher->id)
                ->where('student_id', $student->id)
                ->count();

            if ($usageCount >= $voucher->max_uses_per_student) {
                throw ValidationException::withMessages([
                    'student_id' => ['This student has reached the maximum number of uses for this voucher.'],
                ]);
            }
        }
    }

    /**
     * Redeem a voucher for a student
     */
    public function redeemVoucher(int $studentId, int $voucherId, ?int $appliedByUserId = null): VoucherApplication
    {
        $voucher = VoucherDefinition::findOrFail($voucherId);
        $student = Student::findOrFail($studentId);
        $semester = Semester::query()->where('is_active', true)->first();

        if (! $semester) {
            throw ValidationException::withMessages([
                'voucher_id' => ['No active semester is configured.'],
            ]);
        }

        $this->validateVoucher($voucher, $student, $semester);

        $resolvedAmounts = $this->voucherDiscountAmountResolver->resolveAmounts($voucher, $student, $semester->id);
        $discountAmount = (float) ($resolvedAmounts['discount_amount'] ?? 0);

        if ($voucher->voucher_type === 'discount' && $discountAmount <= 0) {
            throw ValidationException::withMessages([
                'student_id' => ['Unable to calculate a discount amount for the selected student in the active semester.'],
            ]);
        }

        return DB::transaction(function () use ($voucher, $student, $semester, $appliedByUserId, $resolvedAmounts, $discountAmount) {
            return VoucherApplication::create([
                'voucher_definition_id' => $voucher->id,
                'student_id' => $student->id,
                'semester_id' => $semester->id,
                'status' => 'applied',
                'applied_at' => now(),
                'applied_by_user_id' => $appliedByUserId,
                'base_amount' => $resolvedAmounts['base_amount'],
                'discount_amount' => $voucher->voucher_type === 'discount' ? $discountAmount : 0,
            ]);
        });
    }

    /**
     * Get all vouchers for a student
     */
    public function getStudentVouchers(int $studentId): Collection
    {
        return VoucherRedemption::where('student_id', $studentId)
            ->with(['voucher', 'invoice'])
            ->orderBy('redeemed_at', 'desc')
            ->get();
    }

    /**
     * Get active vouchers
     */
    public function getActiveVouchers(): Collection
    {
        return VoucherDefinition::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->orderBy('name')
            ->get();
    }

    /**
     * Get voucher by code
     */
    public function getVoucherByCode(string $code): ?VoucherDefinition
    {
        return VoucherDefinition::where('code', $code)->first();
    }

    /**
     * Check if voucher is valid on a specific date
     */
    public function isVoucherValidOn(string $code, Carbon $date): bool
    {
        $voucher = $this->getVoucherByCode($code);

        if (!$voucher) {
            return false;
        }

        return $voucher->isValidOn($date);
    }

    /**
     * Get redemption count for a voucher
     */
    public function getRedemptionCount(int $voucherId): int
    {
        return VoucherApplication::where('voucher_definition_id', $voucherId)->count();
    }

    /**
     * Get canonical applications for a voucher
     */
    public function getVoucherApplications(int $voucherId): Collection
    {
        return VoucherApplication::query()
            ->where('voucher_definition_id', $voucherId)
            ->with(['student', 'semester', 'invoice'])
            ->orderByDesc('applied_at')
            ->get();
    }
}
