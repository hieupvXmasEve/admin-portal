<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\DeferChargeResolver;
use App\Modules\Finance\Support\BillingScopeHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateBatchChargesAction
{
    public static function run(array $data): array
    {
        $semesterId = (int) $data['semester_id'];
        $chargeTypes = $data['charge_types'];
        $skipIfIssuedOrPaid = $data['skip_if_issued_or_paid'] ?? true;

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
        $query->with(['scholarshipAward.scholarshipDefinition', 'voucherApplications', 'courseRegistrations' => function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        }]);

        $students = $query->get();

        $deferChargeResolver = app(DeferChargeResolver::class);

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
                    $deferCase = $deferChargeResolver->findApplicableFullCase($student, $semesterId);
                    $shouldSkipFullCharges = $deferCase !== null;
                    $didSkipPreserveCharge = false;

                    // Check if Invoice Exists
                    $existingInvoice = StudentInvoice::where('student_id', $student->id)
                        ->where('semester_id', $semesterId)
                        ->first();

                    // Pre-calculation to decide if we should create an invoice
                    $shouldGenInvoice = false;
                    $potentialAmount = 0;

                    // Check Tuition/EGC Amount
                    if ($student->status === 'intake_pre_uni_gc' && in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes)) {
                        if ($shouldSkipFullCharges) {
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
                    if ($student->status === 'intake_course' && in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes)) {
                        if (! $shouldSkipFullCharges) {
                            $amt = self::getTuitionFee($student, $semesterId);
                            if ($amt !== null) {
                                $potentialAmount = $amt;
                                $shouldGenInvoice = true;
                            }
                        }
                    }

                    if ($potentialAmount > 0) {
                        $shouldGenInvoice = true;
                    }

                    // Check Retake (Any registration in this semester prevents 0d skip for Invoice)
                    // Note: Since we removed is_retake from eager load in turn 41, isNotEmpty means any course registration.
                    $hasRegistration = $student->courseRegistrations->isNotEmpty();

                    if ($hasRegistration) {
                        $shouldGenInvoice = true;
                    }

                    // Check Custom Amount
                    if (! empty($data['custom_amount']) && $data['custom_amount'] > 0) {
                        $shouldGenInvoice = true;
                    }

                    // Decision: If no existing invoice AND should not generate -> SKIP
                    if (! $existingInvoice && ! $shouldGenInvoice) {
                        continue;
                    }

                    // 1. Upsert Invoice
                    $invoice = $existingInvoice ?? StudentInvoice::create([
                        'student_id' => $student->id,
                        'semester_id' => $semesterId,
                        'invoice_number' => 'INV-'.time().'-'.$student->student_id.'-'.$semesterId,
                        'due_date' => now()->addDays(30),
                        'opened_at' => now(),
                        'status' => 'draft',
                    ]);

                    if ($invoice->wasRecentlyCreated) {
                        $stats['created_invoices']++;
                    } else {
                        // Skip if policy enabled and invoice is finalizing
                        if ($skipIfIssuedOrPaid && in_array($invoice->status, ['issued', 'paid', 'void'])) {
                            $stats['skipped_count']++;

                            continue;
                        }
                        $stats['updated_invoices']++;
                    }

                    $chargesToLink = [];

                    // 2. Generate based on Status

                    // Case A: Intake Pre-Uni GC -> GC Fee
                    if ($student->status === 'intake_pre_uni_gc' && in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes)) {
                        if ($shouldSkipFullCharges) {
                            $didSkipPreserveCharge = true;
                        } else {
                            $startLevel = $student->gc_current_level ?? 1;
                            $totalLevels = $student->gc_total_levels ?? 6;

                            $levelsToCharge = [$startLevel];
                            if (($startLevel + 1) < $totalLevels) {
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
                                    }
                                }
                            }
                        }
                    }

                    // Case B: Intake Course -> Tuition
                    if ($student->status === 'intake_course' && in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes)) {
                        if ($shouldSkipFullCharges) {
                            $didSkipPreserveCharge = true;
                        } else {
                            $amount = self::getTuitionFee($student, $semesterId);
                            if ($amount !== null) {
                                // Calculate installment index (since intake_major)
                                $paidInst = FinanceCharge::where('student_id', $student->id)
                                    ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
                                    ->where('semester_id', '>=', (int) $student->intake_major)
                                    ->where('semester_id', '<', $semesterId)
                                    ->active()
                                    ->count();
                                $termIdx = $paidInst + 1;

                                // Idempotency check
                                $existingCharge = FinanceCharge::where('student_id', $student->id)
                                    ->where('semester_id', $semesterId)
                                    ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
                                    ->active()
                                    ->first();

                                if ($existingCharge) {
                                    $charge = $existingCharge;
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
                                }

                                // Apply Scholarship only for Tuition students
                                if ($student->scholarshipAward) {
                                    $scholarshipDef = $student->scholarshipAward->scholarshipDefinition;
                                    if ($scholarshipDef) {
                                        $discount = 0;
                                        if ($scholarshipDef->type === 'percentage') {
                                            $discount = ($amount * $scholarshipDef->amount) / 100;
                                        } else {
                                            $discount = $scholarshipDef->amount;
                                        }

                                        if ($discount > 0) {
                                            $charge = self::createChargeIfNotExists(
                                                $student, $semesterId,
                                                FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
                                                -$discount,
                                                'App\Models\StudentScholarshipAward',
                                                $student->scholarshipAward->id
                                            );
                                            if ($charge) {
                                                $chargesToLink[] = $charge;
                                            }
                                        }
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

                                if ($vAmount > 0) {
                                    $charge = self::createChargeIfNotExists(
                                        $student, $semesterId,
                                        FinanceCharge::TYPE_VOUCHER_CREDIT,
                                        -$vAmount,
                                        'App\Models\VoucherApplication',
                                        $voucherApp->id
                                    );
                                    if ($charge) {
                                        $charge->update(['description' => "Voucher Applied ({$voucherApp->code})"]);
                                        $charge->refresh();
                                        $chargesToLink[] = $charge;

                                        // Update voucher app as Used
                                        $voucherApp->update([
                                            'finance_charge_id' => $charge->id,
                                            'invoice_id' => $invoice->id,
                                        ]);
                                    }
                                } else {
                                    // Informational Voucher - Mark as used on this invoice without charge
                                    $voucherApp->update([
                                        'invoice_id' => $invoice->id,
                                    ]);
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
                        }
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

    private static function getTuitionFee(Student $student, int $semesterId): ?float
    {
        $intakeMajor = $student->intake_major;

        if (! $intakeMajor || $semesterId < $intakeMajor) {
            return null;
        }

        $intakeSemester = \App\Models\Semester::find($intakeMajor);
        $targetSemester = \App\Models\Semester::find($semesterId);

        if (! $intakeSemester || ! $targetSemester) {
            return null;
        }

        if ($targetSemester->start_date < $intakeSemester->start_date) {
            return null;
        }

        $termNumber = \App\Models\Semester::where('start_date', '>=', $intakeSemester->start_date)
            ->where('start_date', '<=', $targetSemester->start_date)
            ->count();

        // Find plan based on student's university intake cohort
        $plan = \App\Models\TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
            ->first();

        if (! $plan) {
            return null;
        }

        $term = \App\Models\TuitionPlanTerm::where('tuition_plan_id', $plan->id)
            ->where('term_number', $termNumber)
            ->first();

        return $term ? (float) $term->amount : null;
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
}
