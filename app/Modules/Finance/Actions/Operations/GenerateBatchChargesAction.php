<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Services\DeferChargeResolver;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\BillingScopeHelper;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Modules\Finance\Support\VoucherDiscountAmountResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateBatchChargesAction
{
    public static function run(array $data): array
    {
        $semesterId = (int) $data['semester_id'];
        $chargeTypes = $data['charge_types'];

        // 1. Strict Scope: Only specific statuses and current campus
        $query = BillingScopeHelper::getEligibleStudentsQuery(
            $semesterId,
            $data['scope_type'],
            [
                'program_id' => $data['filter_program_id'] ?? null,
                'enrollment_status' => $data['filter_enrollment_status'] ?? 'all', // Default to all then filter strictly below
                'uploaded_student_ids' => $data['uploaded_student_ids'] ?? [],
            ]
        );

        // Scope by selected charge types: EGC-only => only intake_pre_uni_gc; tuition-only => only intake_course
        $hasEgc = in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes);
        $hasTuition = in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes);
        if ($hasEgc && ! $hasTuition) {
            $query->where('students.status', 'intake_pre_uni_gc');
        } elseif ($hasTuition && ! $hasEgc) {
            $query->where('students.status', 'intake_course');
        } else {
            $query->whereIn('students.status', ['intake_pre_uni_gc', 'intake_course']);
        }

        // Filter by Campus (assuming BillingScopeHelper might already do it, but to be safe)
        if (function_exists('app') && app()->bound('campus')) {
            $campusId = app('campus')->id ?? null;
            if ($campusId) {
                $query->where('students.campus_id', $campusId);
            }
        }

        // Eager load necessary relations for logic check
        $query->with(['scholarshipAward.scholarshipDefinition', 'voucherApplications.voucherDefinition', 'courseRegistrations' => function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        }]);

        $students = $query->get();

        $deferChargeResolver = app(DeferChargeResolver::class);
        $invoiceService = app(InvoiceGenerationService::class);
        $studentChargeTimingResolver = app(StudentChargeTimingResolver::class);
        $voucherDiscountAmountResolver = app(VoucherDiscountAmountResolver::class);
        $students = $students
            ->filter(fn (Student $student) => $studentChargeTimingResolver->shouldIncludeStudentForChargeGeneration($student, $semesterId, $chargeTypes))
            ->values();

        $stats = [
            'total_students' => $students->count(),
            'created_count' => 0,
            'created_invoices' => 0,
            'updated_invoices' => 0,
            'skipped_count' => 0,
            'failed_count' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                try {
                    $canGenerateEgc = in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes, true)
                        && $studentChargeTimingResolver->shouldGenerateEgcForSemester($student, $semesterId);
                    $canGenerateTuition = in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes, true)
                        && $studentChargeTimingResolver->shouldGenerateTuitionForSemester($student, $semesterId);
                    $deferCase = $deferChargeResolver->findApplicableFullCase($student, $semesterId);
                    $shouldSkipFullCharges = $deferCase !== null;
                    $didSkipPreserveCharge = false;

                    $reusableInvoice = self::findReusableInvoice($student->id, $semesterId);
                    $existingTuitionCharge = null;
                    $tuitionScholarshipPendingDiscount = null;

                    // Pre-calculation to decide if we should create an invoice
                    $shouldGenInvoice = false;
                    $potentialAmount = 0;

                    // Check Tuition/EGC Amount
                    if ($canGenerateEgc) {
                        if ($shouldSkipFullCharges) {
                            $potentialAmount = 0;
                        } else {
                            $existingEgcIssuedInInvoice = StudentInvoice::query()
                                ->where('student_id', $student->id)
                                ->where('semester_id', $semesterId)
                                ->whereHas('invoiceLines.charge', function ($query) {
                                    $query->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                                        ->where('status', FinanceCharge::STATUS_ACTIVE);
                                })
                                ->exists();

                            if ($existingEgcIssuedInInvoice) {
                                $potentialAmount = 0;
                            } else {
                                $sLevel = $student->gc_current_level ?? 1;
                                $tLevels = $student->gc_total_levels ?? 6;
                                $potentialAmount = 0;

                                $lvls = [$sLevel];
                                if (($sLevel + 1) < $tLevels) {
                                    $lvls[] = $sLevel + 1;
                                }

                                foreach ($lvls as $l) {
                                    $u = \App\Models\Unit::where('unit_type', 'egc')->where('level', $l)->first();
                                    if ($u) {
                                        $potentialAmount += $u->base_fee;
                                    }
                                }
                            }
                        }
                    }
                    if ($canGenerateTuition) {
                        if (! $shouldSkipFullCharges) {
                            $tuitionTerm = $studentChargeTimingResolver->getTuitionTermData($student, $semesterId);
                            $amt = $tuitionTerm['amount'];

                            if ($amt !== null && $amt > 0) {
                                $existingTuitionCharge = FinanceCharge::where('student_id', $student->id)
                                    ->where('semester_id', $semesterId)
                                    ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
                                    ->active()
                                    ->first();

                                if (! $existingTuitionCharge) {
                                    $potentialAmount = $amt;
                                    $shouldGenInvoice = true;
                                } elseif ($reusableInvoice && self::invoiceHasActiveTuitionLine($reusableInvoice)) {
                                    $tuitionScholarshipPendingDiscount = self::resolveScholarshipDiscountPayload(
                                        $reusableInvoice,
                                        $student->scholarshipAward,
                                        (float) $existingTuitionCharge->amount,
                                    );
                                }
                            }
                        }
                    }

                    if ($potentialAmount > 0) {
                        $shouldGenInvoice = true;
                    }

                    // Check Custom Amount
                    if (! empty($data['custom_amount']) && $data['custom_amount'] > 0) {
                        $shouldGenInvoice = true;
                    }

                    // Decision: If no reusable invoice exists and no new charges are expected -> skip.
                    if (! $reusableInvoice && ! $shouldGenInvoice) {
                        continue;
                    }

                    // 1. Resolve target invoice. Finalized invoices must never block newly generated charges.
                    $invoice = $reusableInvoice ?? self::createDraftInvoice($student, $semesterId);

                    $chargesToLink = [];
                    $pendingDiscounts = [];
                    $hasInvoiceMutation = false;

                    // 2. Generate based on Status

                    // Case A: Intake Pre-Uni GC -> GC Fee
                    if ($canGenerateEgc) {
                        if ($shouldSkipFullCharges) {
                            $didSkipPreserveCharge = true;
                        } else {
                            $existingEgcIssuedInInvoice = StudentInvoice::query()
                                ->where('student_id', $student->id)
                                ->where('semester_id', $semesterId)
                                ->whereHas('invoiceLines.charge', function ($query) {
                                    $query->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                                        ->where('status', FinanceCharge::STATUS_ACTIVE);
                                })
                                ->exists();

                            if ($existingEgcIssuedInInvoice) {
                                // Semester package already issued; do not create any additional EGC level fee.
                            } else {
                                $startLevel = $student->gc_current_level ?? 1;

                                // EGC program: student pays for levels 0–5 only; cap at level 5.
                                $levelsToCharge = $startLevel <= 5 ? [$startLevel] : [];
                                if ($startLevel < 5) {
                                    $levelsToCharge[] = $startLevel + 1;
                                }

                                foreach ($levelsToCharge as $level) {
                                    $unit = \App\Models\Unit::where('unit_type', 'egc')->where('level', $level)->first();
                                    $fee = $unit ? (float) $unit->base_fee : 0;

                                    if ($fee > 0) {
                                        $charge = self::createChargeIfNotExists(
                                            $student, $semesterId,
                                            FinanceCharge::TYPE_EGC_LEVEL_FEE,
                                            $fee,
                                            'App\Models\Unit',
                                            $unit ? $unit->id : 0
                                        );
                                        if ($charge) {
                                            $charge->update(['description' => "EGC Level {$level} Fee"]);
                                            $charge->refresh();
                                            $chargesToLink[] = $charge;
                                            $hasInvoiceMutation = true;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Case B: Intake Course -> Tuition
                    if ($canGenerateTuition) {
                        if ($shouldSkipFullCharges) {
                            $didSkipPreserveCharge = true;
                        } else {
                            $tuitionTerm = $studentChargeTimingResolver->getTuitionTermData($student, $semesterId);
                            $amount = $tuitionTerm['amount'];

                            if ($amount !== null && $amount > 0) {
                                $termIdx = $tuitionTerm['chargeable_term_index'] ?? $tuitionTerm['term_number'] ?? 1;

                                if ($existingTuitionCharge) {
                                    $charge = null;
                                } else {
                                    $charge = self::createChargeIfNotExists(
                                        $student, $semesterId,
                                        FinanceCharge::TYPE_TUITION_TERM,
                                        $amount
                                    );
                                }

                                if ($charge) {
                                    $charge->update(['description' => "Major Tuition (Installment {$termIdx})"]);
                                    $charge->refresh();
                                    $chargesToLink[] = $charge;
                                    $hasInvoiceMutation = true;
                                }

                                $scholarshipDiscount = $charge
                                    ? self::resolveScholarshipDiscountPayload(
                                        $invoice,
                                        $student->scholarshipAward,
                                        (float) $charge->amount,
                                    )
                                    : $tuitionScholarshipPendingDiscount;

                                if ($scholarshipDiscount !== null) {
                                    $pendingDiscounts[] = $scholarshipDiscount;
                                    $hasInvoiceMutation = true;
                                }
                            }
                        }
                    }

                    // 3. Voucher (Global Check)
                    if (in_array('voucher', $chargeTypes) || in_array('all', $chargeTypes)) {
                        foreach ($student->voucherApplications as $voucherApp) {
                            // Rule: invoice_id IS NULL => Not used
                            if (! $voucherApp->invoice_id) {
                                $vAmount = (float) $voucherApp->discount_amount;

                                if ($vAmount <= 0 && $voucherApp->voucherDefinition) {
                                    $resolvedAmounts = $voucherDiscountAmountResolver->resolveAmounts($voucherApp->voucherDefinition, $student, $semesterId);
                                    $vAmount = (float) $resolvedAmounts['discount_amount'];
                                }

                                if ($vAmount > 0) {
                                    $pendingDiscounts[] = [
                                        'discount_type' => 'voucher',
                                        'amount' => $vAmount,
                                        'discount_source' => 'App\Models\VoucherApplication',
                                        'description' => 'Voucher Applied ('.$voucherApp->voucherDefinition?->code.')',
                                        'reference_id' => (int) $voucherApp->id,
                                    ];

                                    $voucherApp->update([
                                        'finance_charge_id' => null,
                                        'invoice_id' => $invoice->id,
                                        'discount_amount' => $vAmount,
                                    ]);
                                    $hasInvoiceMutation = true;
                                } else {
                                    // Informational Voucher - Mark as used on this invoice without charge
                                    $voucherApp->update([
                                        'invoice_id' => $invoice->id,
                                    ]);
                                    $hasInvoiceMutation = true;
                                }
                            }
                        }
                    }

                    // 4. Manual Adjustment
                    if (! empty($data['custom_amount'])) {
                        $charge = self::createChargeIfNotExists(
                            $student, $semesterId,
                            FinanceCharge::TYPE_COURSE_FEE,
                            (float) $data['custom_amount'],
                            'Manual',
                            0 // source_id 0
                        );
                        if ($charge) {
                            $charge->update(['description' => 'Manual Adjustment']);
                            $charge->refresh();
                            $chargesToLink[] = $charge;
                            $hasInvoiceMutation = true;
                        }
                    }

                    if (! $hasInvoiceMutation) {
                        if (! $reusableInvoice) {
                            $invoice->delete();
                        }

                        continue;
                    }

                    if (! $reusableInvoice) {
                        $stats['created_invoices']++;
                    } else {
                        $stats['updated_invoices']++;
                    }

                    // 5. Link Charges
                    foreach ($chargesToLink as $chargeItem) {
                        InvoiceLine::firstOrCreate([
                            'invoice_id' => $invoice->id,
                            'charge_id' => $chargeItem->id,
                        ], [
                            'amount_snapshot' => $chargeItem->amount,
                            'description_snapshot' => $chargeItem->description,
                        ]);
                        $stats['created_count']++;
                    }

                    foreach ($pendingDiscounts as $pendingDiscount) {
                        $invoiceService->applyInvoiceDiscount(
                            $invoice,
                            $pendingDiscount['discount_type'],
                            (float) $pendingDiscount['amount'],
                            $pendingDiscount['discount_source'],
                            $pendingDiscount['description'],
                            $pendingDiscount['reference_id'],
                        );
                    }

                    if ($deferCase && $didSkipPreserveCharge) {
                        $deferChargeResolver->markFullCaseApplied($deferCase, $semesterId);
                    }

                } catch (\Exception $e) {
                    $stats['errors'][] = "Student {$student->student_id}: ".$e->getMessage();
                    $stats['failed_count']++;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch Charge Generation Failed: '.$e->getMessage());
            throw $e;
        }

        return $stats;
    }

    private static function findReusableInvoice(int $studentId, int $semesterId): ?StudentInvoice
    {
        return StudentInvoice::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->reusableForChargeGeneration()
            ->latest('id')
            ->first();
    }

    private static function createDraftInvoice(Student $student, int $semesterId): StudentInvoice
    {
        return StudentInvoice::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'invoice_number' => 'INV-'.time().'-'.$student->student_id.'-'.$semesterId,
            'due_date' => now()->addDays(30),
            'opened_at' => now(),
            'status' => 'draft',
        ]);
    }

    private static function createChargeIfNotExists(Student $student, int $semesterId, string $type, float $amount, ?string $sourceType = null, $sourceId = null): ?FinanceCharge
    {
        $query = FinanceCharge::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', $type);

        if ($sourceType) {
            $query->where('source_type', $sourceType)->where('source_id', $sourceId);
        }

        if ($query->exists()) {
            return $query->first();
        }

        $description = match ($type) {
            FinanceCharge::TYPE_TUITION_TERM => 'Tuition Fee',
            FinanceCharge::TYPE_EGC_LEVEL_FEE => 'EGC Level Fee',
            FinanceCharge::TYPE_SCHOLARSHIP_CREDIT => 'Scholarship Credit',
            FinanceCharge::TYPE_COURSE_FEE => 'Course/Voucher Fee',
            default => 'Charge'
        };

        return FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'charge_type' => $type,
            'description' => $description,
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'active',
            'effective_at' => now(),
        ]);
    }

    private static function invoiceHasActiveTuitionLine(StudentInvoice $invoice): bool
    {
        return InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->whereHas('charge', function ($query) {
                $query->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
                    ->where('status', FinanceCharge::STATUS_ACTIVE);
            })
            ->exists();
    }

    private static function resolveScholarshipDiscountPayload(
        StudentInvoice $invoice,
        ?StudentScholarshipAward $award,
        float $baseAmount,
    ): ?array {
        if (! $award || $baseAmount <= 0) {
            return null;
        }

        $existingScholarshipDiscount = InvoiceDiscount::query()
            ->where('invoice_id', $invoice->id)
            ->where('discount_type', 'scholarship')
            ->where('reference_id', $award->id)
            ->where('discount_source', StudentScholarshipAward::class)
            ->exists();

        if ($existingScholarshipDiscount) {
            return null;
        }

        $scholarshipDef = $award->scholarshipDefinition;
        if (! $scholarshipDef) {
            return null;
        }

        $discount = $scholarshipDef->type === 'percentage'
            ? ($baseAmount * $scholarshipDef->amount) / 100
            : (float) $scholarshipDef->amount;

        if ($discount <= 0) {
            return null;
        }

        return [
            'discount_type' => 'scholarship',
            'amount' => $discount,
            'discount_source' => StudentScholarshipAward::class,
            'description' => 'Scholarship: '.$scholarshipDef->name,
            'reference_id' => (int) $award->id,
        ];
    }
}
