<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Actions\ApplyScholarshipSemesterAdjustmentAction;
use App\Modules\Finance\Actions\Egc\SubmitEgcLevelFeeDebitAction;
use App\Modules\Finance\Actions\Major\SubmitTuitionTermDebitAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\GetActiveScholarshipAdjustmentQuery;
use App\Modules\Finance\Services\DeferChargeResolver;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\BillingScopeHelper;
use App\Modules\Finance\Support\EgcLevelFeeResolver;
use App\Modules\Finance\Support\ScholarshipAdjustmentTimingGuard;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Modules\Finance\Support\VoucherDiscountAmountResolver;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateBatchChargesAction
{
    public static function run(array $data): array
    {
        $semesterId = (int) $data['semester_id'];
        $chargeTypes = $data['charge_types'];
        $dueDate = isset($data['due_date']) ? Carbon::parse($data['due_date']) : now()->addDays(30);

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

        // Scope by selected charge types through the Registry-owned eligibility decision.
        $hasEgc = in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes);
        $hasTuition = in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes);
        $collectionPurposes = match (true) {
            $hasEgc && ! $hasTuition => ['egc'],
            $hasTuition && ! $hasEgc => ['tuition'],
            default => [],
        };
        $campusId = app()->bound('campus') ? app('campus')->id : null;
        $eligibleStudentIds = app(StudentCollectionEligibilityReader::class)->eligibleStudentIds($collectionPurposes, $campusId === null ? null : (int) $campusId);
        $query->whereIn('students.id', $eligibleStudentIds);

        // Eager load necessary relations for logic check
        $query->with(['scholarshipAward.scholarshipDefinition', 'voucherApplications.voucherDefinition', 'courseRegistrations' => function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        }]);

        $students = $query->get();

        $deferChargeResolver = app(DeferChargeResolver::class);
        $invoiceService = app(InvoiceGenerationService::class);
        $studentChargeTimingResolver = app(StudentChargeTimingResolver::class);
        $voucherDiscountAmountResolver = app(VoucherDiscountAmountResolver::class);
        $egcFeeResolver = app(EgcLevelFeeResolver::class);
        $submitTuition = app(SubmitTuitionTermDebitAction::class);
        $submitEgc = app(SubmitEgcLevelFeeDebitAction::class);
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
                    // FIN-REV-020-02 (M2): a fully deferred semester enrollment is
                    // non-billable (PRESERVE and FORFEIT alike), so no charge is
                    // generated and an M1-voided obligation is never resurrected.
                    $shouldSkipFullCharges = $deferChargeResolver->isSemesterEnrollmentDeferred($student, $semesterId);

                    $reusableInvoice = self::findReusableInvoice($student->id, $semesterId);
                    $existingTuitionCharge = null;

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
                                    // FIN-06: canonical EGC fee = Unit.base_fee, fallback flat.
                                    $potentialAmount += $egcFeeResolver->resolve($l);
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
                    $invoice = $reusableInvoice ?? self::createDraftInvoice($student, $semesterId, $dueDate);

                    $chargesToLink = [];
                    $pendingDiscounts = [];
                    $hasInvoiceMutation = false;

                    // 2. Generate based on Status

                    // Case A: Intake Pre-Uni GC -> GC Fee
                    if ($canGenerateEgc) {
                        if ($shouldSkipFullCharges) {
                            // Deferred enrollment is non-billable (FIN-REV-020-02): generate nothing.
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
                                    // FIN-06: same canonical resolver as preview/dedicated flow.
                                    $fee = $egcFeeResolver->resolve($level);

                                    if ($fee > 0) {
                                        $levelDescription = "EGC Level {$level} Fee";

                                        // Dedupe on active charge for this student/semester/level
                                        // description (matches pre-intake batch natural key).
                                        $existingLevelCharge = FinanceCharge::query()
                                            ->where('student_id', $student->id)
                                            ->where('semester_id', $semesterId)
                                            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                                            ->where('status', FinanceCharge::STATUS_ACTIVE)
                                            ->where('description', $levelDescription)
                                            ->first();

                                        if ($existingLevelCharge instanceof FinanceCharge) {
                                            $charge = $existingLevelCharge;
                                        } else {
                                            $intakeResult = $submitEgc->handle(
                                                (int) $student->id,
                                                $semesterId,
                                                (int) $level,
                                                [
                                                    'source_kind' => SubmitEgcLevelFeeDebitAction::SOURCE_KIND_EGC_BATCH,
                                                    'due_date' => $dueDate->toDateString(),
                                                    'invoice_id' => $invoice->id,
                                                    'description' => $levelDescription,
                                                    'generation_mode' => SubmitEgcLevelFeeDebitAction::GENERATION_MODE_BATCH,
                                                ],
                                            );
                                            $charge = FinanceCharge::query()->find($intakeResult->finance_charge_id);
                                        }

                                        if ($charge instanceof FinanceCharge) {
                                            if ($charge->description !== $levelDescription) {
                                                $charge->update(['description' => $levelDescription]);
                                            }
                                            $charge->refresh();
                                            $chargesToLink[] = $charge;
                                            $hasInvoiceMutation = true;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Case B: Intake Course -> Tuition (via Finance Intake Contract)
                    if ($canGenerateTuition) {
                        if ($shouldSkipFullCharges) {
                            // Deferred enrollment is non-billable (FIN-REV-020-02): generate nothing.
                        } else {
                            $tuitionTerm = $studentChargeTimingResolver->getTuitionTermData($student, $semesterId);
                            $amount = $tuitionTerm['amount'];

                            if ($amount !== null && $amount > 0) {
                                $termIdx = $tuitionTerm['chargeable_term_index'] ?? $tuitionTerm['term_number'] ?? 1;
                                $charge = null;
                                $createdViaIntake = false;

                                if ($existingTuitionCharge) {
                                    $charge = null;
                                } else {
                                    $intakeResult = $submitTuition->handle($student, $semesterId, [
                                        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_LEGACY_TUITION,
                                        'due_date' => $dueDate->toDateString(),
                                        'invoice_id' => $invoice->id,
                                        'description' => "Major Tuition (Installment {$termIdx})",
                                    ]);

                                    $charge = FinanceCharge::query()->find($intakeResult->finance_charge_id);
                                    $createdViaIntake = $charge instanceof FinanceCharge;
                                }

                                if ($charge instanceof FinanceCharge) {
                                    $chargesToLink[] = $charge;
                                    $hasInvoiceMutation = true;
                                }

                                // Scholarship: CreateFinanceChargeAction already applies it for new
                                // tuition_term intakes. Re-apply only when reusing an existing charge.
                                if (! $createdViaIntake) {
                                    $chargeAmountForScholarship = $charge
                                        ? (float) $charge->amount
                                        : (float) $existingTuitionCharge->amount;
                                    $scholarshipDiscount = self::resolveScholarshipDiscountPayload(
                                        $invoice,
                                        $student->scholarshipAward,
                                        $chargeAmountForScholarship,
                                    );
                                    if ($scholarshipDiscount !== null) {
                                        $pendingDiscounts[] = $scholarshipDiscount;
                                        $hasInvoiceMutation = true;
                                    }
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

                    if (! $hasInvoiceMutation) {
                        if (! $reusableInvoice) {
                            $billingAccountId = (int) app(BillingAccountProvisioner::class)->forStudent((int) $invoice->student_id)->id;
                            app(SettlementMutationGuard::class)->handle($billingAccountId, function () use ($invoice): void {
                                $invoice->delete();
                            });
                        }

                        continue;
                    }

                    if (! $reusableInvoice) {
                        $stats['created_invoices']++;
                    } else {
                        $stats['updated_invoices']++;
                    }

                    // Debit intake owns charge-to-invoice materialization. Batch
                    // generation only counts the already-materialized results;
                    // re-linking here would bypass the settlement mutation guard.
                    $stats['created_count'] += count($chargesToLink);

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

        // POST-commit only: transition pending_apply scholarship adjustments.
        // Never inside the loop — the whole-loop transaction can roll back, and
        // a status flip must be keyed on the discount actually persisted.
        // applyToLedger re-verifies via the timing guard and refreshes the
        // discount from the committed invoice state before marking applied.
        $pendingAdjustments = ScholarshipSemesterAdjustment::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('target_semester_id', $semesterId)
            ->where('status', ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY)
            ->get();

        foreach ($pendingAdjustments as $adjustment) {
            try {
                app(ApplyScholarshipSemesterAdjustmentAction::class)->applyToLedger($adjustment);
            } catch (\Exception $e) {
                $stats['errors'][] = "Student #{$adjustment->student_id}: adjustment transition failed - ".$e->getMessage();
            }
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

    private static function createDraftInvoice(Student $student, int $semesterId, Carbon $dueDate): StudentInvoice
    {
        $billingAccountId = (int) app(BillingAccountProvisioner::class)->forStudent((int) $student->id)->id;

        return app(SettlementMutationGuard::class)->handle($billingAccountId, function () use ($student, $semesterId, $dueDate): StudentInvoice {
            return StudentInvoice::create([
                'student_id' => $student->id,
                'semester_id' => $semesterId,
                'invoice_number' => 'INV-'.time().'-'.$student->student_id.'-'.$semesterId,
                'due_date' => $dueDate,
                'opened_at' => now(),
                'status' => 'draft',
            ]);
        });
    }

    /**
     * Resolve scholarship discount payload for a tuition charge.
     * No expiry check — business rule: scholarship applies across all semesters.
     */
    private static function resolveScholarshipDiscountPayload(
        StudentInvoice $invoice,
        ?StudentScholarshipAward $award,
        float $baseAmount,
    ): ?array {
        if (! $award || $baseAmount <= 0) {
            return null;
        }

        $scholarshipDef = $award->scholarshipDefinition;
        if (! $scholarshipDef) {
            return null;
        }

        $adjustment = app(GetActiveScholarshipAdjustmentQuery::class)
            ->handle((int) $invoice->student_id, (int) $invoice->semester_id);

        $existingAmount = InvoiceDiscount::query()
            ->where('invoice_id', $invoice->id)
            ->where('discount_type', 'scholarship')
            ->where('reference_id', $award->id)
            ->where('discount_source', StudentScholarshipAward::class)
            ->value('amount');

        // No adjustment + a stored discount: FROZEN. Batch re-runs must never
        // silently rewrite historical scholarship rows (definition edits,
        // multi-charge drift) — only an approved adjustment moves money.
        if ($adjustment === null && $existingAmount !== null) {
            return null;
        }

        // An adjustment may only touch the ledger when the timing guard says
        // the invoice is safe (unpaid, no active DNG). Otherwise leave the row
        // alone — the post-commit transition routes it to finance review.
        if ($adjustment !== null) {
            $guardOutcome = app(ScholarshipAdjustmentTimingGuard::class)
                ->evaluate((int) $invoice->student_id, (int) $invoice->semester_id)['outcome'];

            if ($guardOutcome === ScholarshipAdjustmentTimingGuard::OUTCOME_REVIEW) {
                return null;
            }
        }

        $resolver = app(ScholarshipDiscountResolver::class);

        // FIN-04/07: shared resolver + whole-invoice tuition base — the single
        // upserted discount row covers ALL tuition charges (preview/execute
        // parity, multi-charge safety).
        $invoiceBase = $resolver->invoiceTuitionBase($invoice);
        $discount = $resolver->resolveAdjusted(
            $scholarshipDef,
            $invoiceBase > 0 ? $invoiceBase : $baseAmount,
            $adjustment,
        );

        if ($existingAmount !== null && abs((float) $existingAmount - $discount) < 0.01) {
            return null;
        }

        if ($existingAmount === null && $discount <= 0) {
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
