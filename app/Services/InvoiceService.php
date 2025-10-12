<?php

namespace App\Services;

use App\Models\BillingCycle;
use App\Models\Enrollment;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceItem;
use App\Models\ScholarshipDefinition;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentScholarshipAward;
use App\Models\TuitionPlanTerm;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Generate invoices for all enrolled students in a billing cycle.
     *
     * @return array{invoices: Collection, stats: array{created: int, updated: int, total: int}}
     */
    public function generateInvoicesForCycle(int $cycleId): array
    {
        $cycle = BillingCycle::with('semester')->findOrFail($cycleId);

        if (! $cycle->isActive()) {
            throw new \Exception('Only active billing cycles can generate invoices.');
        }

        // Get all enrolled students for this semester
        // Only get students with status that will have invoice items
        $enrollments = Enrollment::where('semester_id', $cycle->semester_id)
            ->where('status', 'in_progress')
            ->with(['student' => function ($query) {
                $query->whereIn('status', ['intake_course', 'intake_pre_uni_gc']);
            }])
            ->get()
            ->filter(function ($enrollment) use ($cycle) {
                $student = $enrollment->student;

                // Filter out null students
                if (! $student) {
                    return false;
                }

                // Only process intake_pre_uni_gc students with gc levels set
                if ($student->status === 'intake_pre_uni_gc') {
                    return ! is_null($student->gc_starting_level) && ! is_null($student->gc_current_level);
                }

                // For intake_course students: only allow if they have a valid TuitionPlanTerm
                if ($student->status === 'intake_course') {
                    // Must have curriculum_version_id and intake_semester_id
                    if (! $student->curriculum_version_id || ! $student->intake_semester_id) {
                        return false;
                    }

                    // Check if TuitionPlanTerm exists with amount > 0
                    $term = TuitionPlanTerm::whereHas('tuitionPlan', function ($query) use ($student) {
                        $query->where('curriculum_version_id', $student->curriculum_version_id)
                            ->where('intake_semester_id', $student->intake_semester_id)
                            ->where('is_active', true);
                    })
                        ->where('semester_id', $cycle->semester_id)
                        ->where('amount', '>', 0)
                        ->exists();

                    return $term;
                }

                return false;
            });

        $invoices = new Collection;
        $createdCount = 0;
        $updatedCount = 0;

        // Process each enrollment individually (avoid nested transactions)
        foreach ($enrollments as $enrollment) {
            try {
                // Check if invoice already exists for this student and cycle
                $existingInvoice = StudentInvoice::where('student_id', $enrollment->student_id)
                    ->where('billing_cycle_id', $cycle->id)
                    ->first();

                if ($existingInvoice) {
                    // Invoice exists: apply any new pending discounts
                    $this->applyPendingDiscounts($existingInvoice);
                    $invoices->push($existingInvoice);
                    $updatedCount++;
                } else {
                    // Generate new invoice
                    $invoice = $this->generateInvoiceForStudent(
                        $enrollment->student,
                        $cycle
                    );
                    $invoices->push($invoice);
                    $createdCount++;
                }
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle duplicate key error (unique constraint violation)
                if ($e->getCode() === '23000') {
                    // Fetch the existing invoice created by concurrent request
                    $existingInvoice = StudentInvoice::where('student_id', $enrollment->student_id)
                        ->where('billing_cycle_id', $cycle->id)
                        ->first();

                    if ($existingInvoice) {
                        // Apply pending discounts to existing invoice
                        $this->applyPendingDiscounts($existingInvoice);
                        $invoices->push($existingInvoice);
                        $updatedCount++;
                    }
                } else {
                    // Re-throw other exceptions
                    throw $e;
                }
            }
        }

        return [
            'invoices' => $invoices,
            'stats' => [
                'created' => $createdCount,
                'updated' => $updatedCount,
                'total' => $invoices->count(),
            ],
        ];
    }

    /**
     * Generate an invoice for a specific student in a billing cycle.
     *
     * @param  Student|int  $student  Student model or student ID
     * @param  BillingCycle|int  $cycle  BillingCycle model or cycle ID
     */
    public function generateInvoiceForStudent(Student|int $student, BillingCycle|int $cycle): StudentInvoice
    {
        // Allow passing objects or IDs for flexibility
        if (is_int($student)) {
            $student = Student::findOrFail($student);
        }

        if (is_int($cycle)) {
            $cycle = BillingCycle::with('semester')->findOrFail($cycle);
        }

        return DB::transaction(function () use ($student, $cycle) {
            // Create the invoice
            $invoice = StudentInvoice::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'student_id' => $student->id,
                'billing_cycle_id' => $cycle->id,
                'semester_id' => $cycle->semester_id,
                'subtotal' => 0,
                'discount_total' => 0,
                'total_amount' => 0,
                'paid_amount' => 0,
                'status' => 'pending',
                'due_date' => $cycle->due_date,
            ]);

            // Add tuition items from tuition plan
            $this->addTuitionItems($invoice, $student, $cycle->semester_id);

            // Add unit fees from course registrations
            $this->addUnitFeeItems($invoice, $student, $cycle->semester_id);

            // Apply scholarship discounts automatically (calculates subtotal internally)
            $this->applyScholarshipDiscount($invoice);

            // Apply voucher discounts automatically (calculates subtotal internally)
            $this->applyVoucherDiscounts($invoice);

            // Recalculate totals once at the end
            $invoice->recalculateTotals();

            return $invoice->fresh(['items', 'discounts']);
        });
    }

    /**
     * Add tuition items to an invoice based on the student's tuition plan.
     * Only applies to students with status = 'intake_course'.
     * Creates bill for ONE tuition term per semester (prevents duplicates).
     * Scholarships are automatically applied via applyScholarshipDiscount().
     */
    protected function addTuitionItems(StudentInvoice $invoice, Student $student, int $semesterId): void
    {
        // Only add tuition items for students with status = 'intake_course'
        if ($student->status !== 'intake_course') {
            return;
        }

        if (! $student->curriculum_version_id || ! $student->intake_semester_id) {
            return;
        }

        // Find the tuition plan term for this semester
        $term = TuitionPlanTerm::whereHas('tuitionPlan', function ($query) use ($student) {
            $query->where('curriculum_version_id', $student->curriculum_version_id)
                ->where('intake_semester_id', $student->intake_semester_id)
                ->where('is_active', true);
        })
            ->where('semester_id', $semesterId)
            ->first();

        if ($term && $term->amount > 0) {
            // Check if this term already has an invoice item for this student in this semester
            $existingItem = InvoiceItem::whereHas('invoice', function ($query) use ($student, $semesterId) {
                $query->where('student_id', $student->id)
                    ->where('semester_id', $semesterId);
            })
                ->where('reference_type', TuitionPlanTerm::class)
                ->where('reference_id', $term->id)
                ->exists();

            if ($existingItem) {
                return; // Skip if already billed for this term in this semester
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => 'tuition',
                'description' => "Tuition Fee - Term {$term->term_number}",
                'quantity' => 1,
                'unit_price' => $term->amount,
                'total_price' => $term->amount,
                'reference_id' => $term->id,
                'reference_type' => TuitionPlanTerm::class,
            ]);
        }
    }

    /**
     * Add unit fee items to an invoice for EGC students.
     * Only applies to students with status = 'intake_pre_uni_gc'.
     * Creates bill for ONE unit matching student's gc_current_level.
     * Requires student to have both gc_starting_level and gc_current_level set.
     */
    protected function addUnitFeeItems(StudentInvoice $invoice, Student $student, int $semesterId): void
    {
        // Only add unit fee items for students with status = 'intake_pre_uni_gc'
        if ($student->status !== 'intake_pre_uni_gc') {
            return;
        }

        // Require both gc_starting_level and gc_current_level to be set
        if (is_null($student->gc_starting_level) || is_null($student->gc_current_level)) {
            return;
        }

        // Find ONE unit with type='egc' matching student's gc_current_level
        // If multiple units exist, get the most recently created one
        $unit = \App\Models\Unit::where('unit_type', 'egc')
            ->where('level', $student->gc_current_level)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $unit) {
            return;
        }

        // Check if this unit already has an invoice item for this student in this semester
        $existingItem = InvoiceItem::whereHas('invoice', function ($query) use ($student, $semesterId) {
            $query->where('student_id', $student->id)
                ->where('semester_id', $semesterId);
        })
            ->where('reference_type', \App\Models\Unit::class)
            ->where('reference_id', $unit->id)
            ->exists();

        if ($existingItem) {
            return; // Skip if already billed for this unit in this semester
        }

        // Use base_fee (not retake_fee for EGC)
        $fee = $unit->base_fee;

        // Skip if no fee is set
        if (! $fee || $fee <= 0) {
            return;
        }

        // Create invoice item
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'egc',
            'description' => $unit->name,
            'quantity' => 1,
            'unit_price' => $fee,
            'total_price' => $fee,
            'reference_id' => $unit->id,
            'reference_type' => \App\Models\Unit::class,
        ]);
    }

    /**
     * Apply scholarship discount to an invoice.
     * Only applies to students with status = 'intake_course'.
     */
    public function applyScholarshipDiscount(StudentInvoice $invoice): void
    {
        $student = Student::find($invoice->student_id);

        // Only apply scholarship for students with status = 'intake_course'
        if (! $student || $student->status !== 'intake_course') {
            return;
        }

        // Get the student's scholarship award
        $award = StudentScholarshipAward::where('student_id', $invoice->student_id)
            ->with('scholarship')
            ->first();

        if (! $award || ! $award->scholarship) {
            return;
        }

        $scholarship = $award->scholarship;

        // Check if scholarship is valid for the invoice date
        $invoiceDate = $invoice->created_at ?? now();
        if (! $this->isScholarshipValid($scholarship, $invoiceDate)) {
            return;
        }

        // Calculate subtotal from invoice items directly (always fresh)
        $subtotal = $invoice->items()->sum('total_price');

        // Calculate discount amount using fresh subtotal
        $discountAmount = $this->calculateScholarshipDiscount($scholarship, $subtotal);

        if ($discountAmount > 0) {
            // Check if discount already exists
            $existingDiscount = InvoiceDiscount::where('invoice_id', $invoice->id)
                ->where('discount_type', 'scholarship')
                ->where('reference_id', $scholarship->id)
                ->first();

            if (! $existingDiscount) {
                InvoiceDiscount::create([
                    'invoice_id' => $invoice->id,
                    'discount_type' => 'scholarship',
                    'discount_source' => $scholarship->code,
                    'description' => $scholarship->name,
                    'amount' => $discountAmount,
                    'reference_id' => $scholarship->id,
                ]);
            }
        }
    }

    /**
     * Apply voucher discounts to an invoice.
     * Supports both 'discount' vouchers (with amount) and 'informational' vouchers (amount = 0).
     * Informational vouchers are tracked in invoice_discounts for display purposes only.
     */
    protected function applyVoucherDiscounts(StudentInvoice $invoice): void
    {
        // Get pending voucher redemptions for this student and billing cycle
        $redemptions = \App\Models\VoucherRedemption::where('student_id', $invoice->student_id)
            ->where('billing_cycle_id', $invoice->billing_cycle_id)
            ->where('status', 'pending')
            ->with('voucher')
            ->get();

        foreach ($redemptions as $redemption) {
            $voucher = $redemption->voucher;

            // Skip if voucher is not active or expired
            if (! $voucher->is_active ||
                now()->lessThan($voucher->valid_from) ||
                now()->greaterThan($voucher->valid_until)) {
                continue;
            }

            // Calculate discount amount based on voucher type
            $discountAmount = 0;

            if ($voucher->voucher_type === 'discount') {
                // Calculate subtotal from invoice items directly (always fresh)
                $subtotal = $invoice->items()->sum('total_price');

                // Calculate discount using fresh subtotal
                if ($voucher->discount_type === 'percentage') {
                    $discountAmount = round(($subtotal * $voucher->discount_value) / 100, 2);
                } else {
                    $discountAmount = min($voucher->discount_value, $subtotal);
                }
            }
            // For 'informational' or other types, amount remains 0 but still track it

            // Create invoice discount record (even if amount is 0 for informational vouchers)
            InvoiceDiscount::create([
                'invoice_id' => $invoice->id,
                'discount_type' => 'voucher',
                'discount_source' => $voucher->code,
                'description' => $voucher->name,
                'amount' => $discountAmount,
                'reference_id' => $voucher->id,
            ]);

            // Mark redemption as redeemed
            $redemption->update([
                'status' => 'redeemed',
                'redeemed_at' => now(),
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    /**
     * Apply pending discounts (scholarships and vouchers) to an existing invoice.
     * This is used when re-generating invoices to apply new discounts added after initial generation.
     */
    protected function applyPendingDiscounts(StudentInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // Apply pending scholarships
            $this->applyScholarshipDiscount($invoice);

            // Apply pending vouchers
            $this->applyVoucherDiscounts($invoice);

            // Recalculate totals after applying discounts
            $invoice->recalculateTotals();
        });
    }

    /**
     * Check if a scholarship is valid for a given date.
     */
    protected function isScholarshipValid(ScholarshipDefinition $scholarship, Carbon $date): bool
    {
        return $scholarship->is_active &&
            $date->greaterThanOrEqualTo($scholarship->valid_from) &&
            $date->lessThanOrEqualTo($scholarship->valid_until);
    }

    /**
     * Calculate scholarship discount amount.
     */
    protected function calculateScholarshipDiscount(ScholarshipDefinition $scholarship, float $subtotal): float
    {
        if ($scholarship->type === 'percentage') {
            return round(($subtotal * $scholarship->amount) / 100, 2);
        }

        // Fixed amount
        return min($scholarship->amount, $subtotal);
    }

    /**
     * Generate a unique invoice number.
     */
    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');

        // Get the last invoice number for today
        $lastInvoice = StudentInvoice::where('invoice_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            // Extract the sequence number and increment
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $sequence = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }

        return "{$prefix}-{$date}-{$sequence}";
    }

    /**
     * Add an invoice item.
     */
    public function addInvoiceItem(int $invoiceId, array $itemData): InvoiceItem
    {
        $invoice = StudentInvoice::findOrFail($invoiceId);

        $itemData['invoice_id'] = $invoiceId;
        $itemData['quantity'] = $itemData['quantity'] ?? 1;

        return DB::transaction(function () use ($itemData) {
            return InvoiceItem::create($itemData);
        });
    }

    /**
     * Update an invoice item.
     */
    public function updateInvoiceItem(int $itemId, array $data): InvoiceItem
    {
        $item = InvoiceItem::findOrFail($itemId);

        return DB::transaction(function () use ($item, $data) {
            $item->update($data);

            return $item->fresh();
        });
    }

    /**
     * Delete an invoice item.
     */
    public function deleteInvoiceItem(int $itemId): bool
    {
        $item = InvoiceItem::findOrFail($itemId);

        return DB::transaction(function () use ($item) {
            return $item->delete();
        });
    }

    /**
     * Apply a voucher discount to an invoice.
     */
    public function applyVoucherDiscount(int $invoiceId, int $voucherId): InvoiceDiscount
    {
        $invoice = StudentInvoice::findOrFail($invoiceId);
        $voucher = \App\Models\VoucherDefinition::findOrFail($voucherId);

        // Check if voucher is valid
        $now = now();
        if (! $voucher->is_active ||
            $now->lessThan($voucher->valid_from) ||
            $now->greaterThan($voucher->valid_until)) {
            throw new \Exception('This voucher is not valid.');
        }

        // Only apply discount if voucher type is discount
        if ($voucher->voucher_type !== 'discount') {
            throw new \Exception('This voucher does not provide a discount.');
        }

        // Calculate discount amount
        $discountAmount = 0;
        if ($voucher->discount_type === 'percentage') {
            $discountAmount = round(($invoice->subtotal * $voucher->discount_value) / 100, 2);
        } else {
            $discountAmount = min($voucher->discount_value, $invoice->subtotal);
        }

        return DB::transaction(function () use ($invoice, $voucher, $discountAmount) {
            return InvoiceDiscount::create([
                'invoice_id' => $invoice->id,
                'discount_type' => 'voucher',
                'discount_source' => $voucher->code,
                'description' => $voucher->name,
                'amount' => $discountAmount,
                'reference_id' => $voucher->id,
            ]);
        });
    }

    /**
     * Calculate invoice total.
     */
    public function calculateInvoiceTotal(StudentInvoice $invoice): void
    {
        $invoice->recalculateTotals();
    }

    /**
     * Mark an invoice as paid.
     */
    public function markInvoiceAsPaid(int $invoiceId): StudentInvoice
    {
        $invoice = StudentInvoice::findOrFail($invoiceId);

        return DB::transaction(function () use ($invoice) {
            $invoice->markAsPaid();

            return $invoice->fresh();
        });
    }
}
