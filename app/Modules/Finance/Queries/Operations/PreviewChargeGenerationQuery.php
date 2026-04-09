<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Services\DeferChargeResolver;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Modules\Finance\Support\VoucherDiscountAmountResolver;

class PreviewChargeGenerationQuery
{
    public function handle(array $params): array
    {
        $semesterId = (int) $params['semester_id'];
        $chargeTypes = $params['charge_types'];

        $query = \App\Modules\Finance\Support\BillingScopeHelper::getEligibleStudentsQuery(
            (int) $semesterId,
            $params['scope_type'],
            [
                'program_id' => $params['filter_program_id'] ?? null,
                'enrollment_status' => $params['filter_enrollment_status'] ?? 'all',
                'uploaded_student_ids' => $params['uploaded_student_ids'] ?? [],
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

        // Eager load necessary relations
        $query->with(['scholarshipAward.scholarshipDefinition', 'voucherApplications.voucherDefinition', 'courseRegistrations' => function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        }]);

        $students = $query->limit(500)->get();

        $deferChargeResolver = app(DeferChargeResolver::class);
        $studentChargeTimingResolver = app(StudentChargeTimingResolver::class);
        $voucherDiscountAmountResolver = app(VoucherDiscountAmountResolver::class);
        $students = $students
            ->filter(fn (Student $student) => $studentChargeTimingResolver->shouldIncludeStudentForChargeGeneration($student, $semesterId, $chargeTypes))
            ->values();

        $previewItems = [];
        $newChargesCount = 0;
        $skipCount = 0;
        $totalAmount = 0;
        $warnings = [];

        foreach ($students as $student) {
            $canGenerateEgc = in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes, true)
                && $studentChargeTimingResolver->shouldGenerateEgcForSemester($student, $semesterId);
            $canGenerateTuition = in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes, true)
                && $studentChargeTimingResolver->shouldGenerateTuitionForSemester($student, $semesterId);
            $breakdown = [];
            $studentTotal = 0;
            $grossAmount = 0;
            $hasExistingCharge = false;

            $deferCase = $deferChargeResolver->findApplicableFullCase($student, $semesterId);
            $shouldSkipFullCharges = $deferCase !== null;

            $reusableInvoice = StudentInvoice::query()
                ->where('student_id', $student->id)
                ->where('semester_id', $semesterId)
                ->reusableForChargeGeneration()
                ->latest('id')
                ->first();

            $hasReusableInvoice = $reusableInvoice !== null;
            $hasFinalizedInvoice = StudentInvoice::query()
                ->where('student_id', $student->id)
                ->where('semester_id', $semesterId)
                ->whereIn('status', StudentInvoice::NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES)
                ->exists();
            $hasExistingInvoice = $hasReusableInvoice || $hasFinalizedInvoice;

            // 1. Calculate based on Status

            // Case A: Pre-Uni GC
            if ($canGenerateEgc) {
                if ($shouldSkipFullCharges) {
                    $breakdown[] = ['label' => 'EGC Fee (Deferred)', 'amount' => 0];
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
                        // Semester package already issued; skip the entire EGC generation for this student.
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
                                // Check specific level existence
                                $exists = FinanceCharge::where('student_id', $student->id)
                                    ->where('semester_id', $semesterId)
                                    ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                                    ->where('description', 'like', "%Start Level {$level}%")
                                    ->active()
                                    ->exists();

                                // Legacy check
                                if (! $exists) {
                                    $exists = FinanceCharge::where('student_id', $student->id)
                                        ->where('semester_id', $semesterId)
                                        ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                                        ->where('description', 'like', "%Level {$level}%")
                                        ->active()
                                        ->exists();
                                }

                                if ($exists) {
                                    $breakdown[] = ['label' => "GC Level {$level} (Skipped)", 'amount' => 0];
                                    $hasExistingCharge = true;
                                } else {
                                    $breakdown[] = ['label' => "EGC Level {$level} Fee", 'amount' => $fee];
                                    $studentTotal += $fee;
                                    $grossAmount += $fee;
                                }
                            }
                        }
                    }
                }
            }

            // Case B: Course Tuition
            if ($canGenerateTuition) {
                if ($shouldSkipFullCharges) {
                    $breakdown[] = ['label' => 'Tuition (Deferred)', 'amount' => 0];
                } else {
                    $tuitionTerm = $studentChargeTimingResolver->getTuitionTermData($student, $semesterId);
                    $amount = $tuitionTerm['amount'];

                    if ($amount !== null && $amount > 0) {
                        $existingTuitionCharge = $this->findExistingCharge($student, $semesterId, FinanceCharge::TYPE_TUITION_TERM);

                        if ($existingTuitionCharge) {
                            // Only preview scholarship update when there is a reusable invoice to mutate;
                            // without one the student will be skipped in generation entirely.
                            if ($reusableInvoice) {
                                $scholarshipPreview = $this->buildScholarshipPreview(
                                    $reusableInvoice,
                                    $student->scholarshipAward,
                                    (float) $existingTuitionCharge->amount,
                                );
                                if ($scholarshipPreview !== null) {
                                    $breakdown[] = $scholarshipPreview;
                                    $studentTotal -= abs((float) $scholarshipPreview['amount']);
                                }
                            }
                        } else {
                            $breakdown[] = ['label' => 'Tuition (Major)', 'amount' => $amount];
                            $studentTotal += $amount;
                            $grossAmount += $amount;

                            $scholarshipPreview = $this->buildScholarshipPreview(
                                $reusableInvoice,
                                $student->scholarshipAward,
                                $amount,
                            );
                            if ($scholarshipPreview !== null) {
                                $breakdown[] = $scholarshipPreview;
                                $studentTotal -= abs((float) $scholarshipPreview['amount']);
                            }
                        }
                    }
                }
            }

            // 2. Voucher (Global)
            if (in_array('voucher', $chargeTypes) || in_array('all', $chargeTypes)) {

                foreach ($student->voucherApplications as $voucherApp) {
                    if (! $voucherApp->invoice_id) {
                        $vAmount = (float) $voucherApp->discount_amount;

                        if ($vAmount <= 0 && $voucherApp->voucherDefinition) {
                            $resolvedAmounts = $voucherDiscountAmountResolver->resolveAmounts($voucherApp->voucherDefinition, $student, $semesterId);
                            $vAmount = (float) $resolvedAmounts['discount_amount'];
                        }

                        if ($vAmount > 0) {
                            $breakdown[] = [
                                'label' => "Voucher ({$voucherApp->voucherDefinition?->code})",
                                'amount' => -$vAmount,
                            ];
                            $studentTotal -= $vAmount;
                        } else {
                            // Informational Voucher
                            $breakdown[] = [
                                'label' => "Voucher Info ({$voucherApp->voucherDefinition?->code})",
                                'amount' => 0,
                            ];
                        }
                    }
                }
            }

            // 3. Manual
            if (! empty($params['custom_amount'])) {
                $breakdown[] = ['label' => 'Custom Fee', 'amount' => (float) $params['custom_amount']];
                $studentTotal += (float) $params['custom_amount'];
                $grossAmount += (float) $params['custom_amount'];
            }

            // Determine Invoice Eligibility
            $shouldGenInvoice = $grossAmount > 0;

            $willCreateInvoice = $shouldGenInvoice && ! $hasReusableInvoice;

            if ($willCreateInvoice || ($hasExistingInvoice && ($studentTotal != 0 || ! empty($breakdown)))) {
                if ($willCreateInvoice) {
                    // Start new invoice process
                    $newChargesCount++;
                } else {
                    // Update existing
                    if ($studentTotal != 0) {
                        $newChargesCount++;
                    }
                }

                $totalAmount += $studentTotal;
            } else {
                if ($hasExistingCharge || $hasExistingInvoice) {
                    $skipCount++; // Skipped because done
                }
                // Else: Skipped because not eligible (0$ and no retake)
            }

            $studentTypeLabel = $canGenerateTuition ? 'Course' : ($canGenerateEgc ? 'EGC' : $student->status);

            $previewItems[] = [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'student_type' => $studentTypeLabel,
                'has_existing_charge' => $hasExistingCharge,
                'estimated_amount' => $studentTotal,
                'warning' => $hasExistingCharge
                    ? 'Existing charges found'
                    : ($willCreateInvoice && $hasFinalizedInvoice
                        ? 'Existing finalized invoice found; a new invoice will be created for new charges'
                        : ($shouldGenInvoice ? null : 'No eligible charges (0đ)')),
                'breakdown' => $breakdown,
                'will_create_invoice' => $willCreateInvoice,
            ];
        }

        // Filter out no-action items
        $filteredItems = array_filter($previewItems, function ($item) {
            return $item['will_create_invoice']
                || $item['has_existing_charge']
                || $item['estimated_amount'] != 0
                || ! empty($item['breakdown']);
        });
        $filteredItems = array_values($filteredItems);

        return [
            'students' => $filteredItems,
            'total_students' => count($filteredItems),
            'new_charges_count' => $newChargesCount,
            'skip_count' => $skipCount,
            'total_amount' => $totalAmount,
            'warnings' => $warnings,
        ];
    }

    private function findExistingCharge(Student $student, int $semesterId, string $type): ?FinanceCharge
    {
        return FinanceCharge::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', $type)
            ->active()
            ->first();
    }

    /**
     * Build scholarship discount preview entry.
     * No expiry check — business rule: scholarship applies to all semesters.
     * No invoice required — works for both new and existing students.
     */
    private function buildScholarshipPreview(
        ?StudentInvoice $invoice,
        ?StudentScholarshipAward $award,
        float $baseAmount,
    ): ?array {
        if (! $award || $baseAmount <= 0) {
            return null;
        }

        // If invoice already exists, prevent showing duplicate scholarship discount
        if ($invoice) {
            $alreadyApplied = InvoiceDiscount::query()
                ->where('invoice_id', $invoice->id)
                ->where('discount_type', 'scholarship')
                ->where('reference_id', $award->id)
                ->exists();

            if ($alreadyApplied) {
                return null;
            }
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
            'label' => "Scholarship ({$scholarshipDef->code})",
            'amount' => -$discount,
        ];
    }
}
