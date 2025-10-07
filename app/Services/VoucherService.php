<?php

namespace App\Services;

use App\Models\VoucherDefinition;
use App\Models\VoucherRedemption;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
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

        // Check if voucher has been redeemed
        if ($voucher->redemptions()->exists()) {
            throw ValidationException::withMessages([
                'voucher' => ['Cannot delete voucher that has been redeemed by students.']
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
    public function validateVoucher(string $code, int $studentId, ?int $invoiceId = null): bool
    {
        $voucher = VoucherDefinition::where('code', $code)->first();

        if (!$voucher) {
            throw ValidationException::withMessages([
                'code' => ['Voucher code not found.']
            ]);
        }

        if (!$voucher->is_active) {
            throw ValidationException::withMessages([
                'code' => ['This voucher is not active.']
            ]);
        }

        if (!$voucher->isCurrentlyValid()) {
            throw ValidationException::withMessages([
                'code' => ['This voucher is not valid at this time.']
            ]);
        }

        // Check if student exists
        if (!Student::find($studentId)) {
            throw ValidationException::withMessages([
                'student_id' => ['Student not found.']
            ]);
        }

        // Check if already redeemed by this student for this invoice
        if ($invoiceId) {
            $existingRedemption = VoucherRedemption::where('voucher_id', $voucher->id)
                ->where('student_id', $studentId)
                ->where('invoice_id', $invoiceId)
                ->exists();

            if ($existingRedemption) {
                throw ValidationException::withMessages([
                    'code' => ['This voucher has already been redeemed for this invoice.']
                ]);
            }
        }

        return true;
    }

    /**
     * Redeem a voucher for a student
     */
    public function redeemVoucher(int $studentId, string $code, ?int $invoiceId = null): VoucherRedemption
    {
        $this->validateVoucher($code, $studentId, $invoiceId);

        $voucher = VoucherDefinition::where('code', $code)->firstOrFail();

        return DB::transaction(function () use ($voucher, $studentId, $invoiceId) {
            return VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'student_id' => $studentId,
                'invoice_id' => $invoiceId,
                'redeemed_at' => now(),
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
        return VoucherRedemption::where('voucher_id', $voucherId)->count();
    }

    /**
     * Get redemptions for a voucher
     */
    public function getVoucherRedemptions(int $voucherId): Collection
    {
        return VoucherRedemption::where('voucher_id', $voucherId)
            ->with(['student', 'invoice'])
            ->orderBy('redeemed_at', 'desc')
            ->get();
    }
}
