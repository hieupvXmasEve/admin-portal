<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Models\FinanceCharge;
use App\Models\Student;

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

        // Explicit filter
        $query->whereIn('students.status', ['intake_pre_uni_gc', 'intake_course']);
         // Filter by Campus (assuming BillingScopeHelper might already do it, but to be safe)
        if (function_exists('app') && app()->bound('campus')) {
            $campusId = app('campus')->id ?? null;
            if ($campusId) {
                $query->where('students.campus_id', $campusId);
            }
        }

        // Eager load necessary relations
        $query->with(['scholarshipAward.scholarshipDefinition', 'voucherApplications', 'courseRegistrations' => function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        }]);

        $students = $query->limit(500)->get();

        $previewItems = [];
        $newChargesCount = 0;
        $skipCount = 0;
        $totalAmount = 0;
        $warnings = [];

        foreach ($students as $student) {
            $breakdown = [];
            $studentTotal = 0;
            $grossAmount = 0;
            $hasExistingCharge = false;
            
            // Check Existing Invoice
            $hasExistingInvoice = \App\Models\StudentInvoice::where('student_id', $student->id)
                ->where('semester_id', $semesterId)
                ->exists();
            
            $hasRetake = $student->courseRegistrations->isNotEmpty();

            // 1. Calculate based on Status
            
            // Case A: Pre-Uni GC
            if ($student->status === 'intake_pre_uni_gc' && in_array(FinanceCharge::TYPE_EGC_LEVEL_FEE, $chargeTypes)) {
                $startLevel = $student->gc_current_level ?? 1;
                $totalLevels = $student->gc_total_levels ?? 6; 

                $levelsToCharge = [$startLevel];
                // Rule: Charge next level if within bounds (0 to total-1)
                // gc_total_levels is count (e.g. 6). Indices are 0..5.
                // Next level must be < total.
                if (($startLevel + 1) < $totalLevels) {
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
                         if (!$exists) {
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

            // Case B: Course Tuition
            if ($student->status === 'intake_course' && in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes)) {
                $amount = $this->getTuitionFee($student, $semesterId);
                if ($amount !== null) {
                    if ($this->checkChargeExists($student, $semesterId, FinanceCharge::TYPE_TUITION_TERM)) {
                        $breakdown[] = ['label' => 'Tuition (Skipped)', 'amount' => 0];
                        $hasExistingCharge = true;
                    } else {
                        $breakdown[] = ['label' => 'Tuition (Major)', 'amount' => $amount];
                        $studentTotal += $amount;
                        $grossAmount += $amount;

                         // Scholarship (Only for Course Tuition)
                         if ($student->scholarshipAward) {
                             $exists = FinanceCharge::where('student_id', $student->id)
                                ->where('semester_id', $semesterId)
                                ->where('charge_type', FinanceCharge::TYPE_SCHOLARSHIP_CREDIT)
                                ->exists();
                             
                             if ($exists) {
                                 $breakdown[] = ['label' => 'Scholarship (Skipped)', 'amount' => 0];
                                 $hasExistingCharge = true;
                             } else {
                                 $scholarshipDef = $student->scholarshipAward->scholarshipDefinition;
                                 if ($scholarshipDef) {
                                    $sAmount = 0;
                                    if ($scholarshipDef->type === 'percentage') {
                                        $sAmount = ($amount * $scholarshipDef->amount) / 100;
                                    } else {
                                        $sAmount = $scholarshipDef->amount;
                                    }

                                    if ($sAmount > 0) {
                                        $breakdown[] = [
                                            'label' => "Scholarship ({$scholarshipDef->code})", 
                                            'amount' => -$sAmount
                                        ];
                                        $studentTotal -= $sAmount;
                                    }
                                 }
                             }
                         }
                    }
                }
            }

            // 2. Voucher (Global)
            if (in_array('voucher', $chargeTypes) || in_array('all', $chargeTypes)) {

                 foreach ($student->voucherApplications as $voucherApp) {
                     if (!$voucherApp->invoice_id) {
                         $vAmount = (float) $voucherApp->discount_amount;
                         
                         if ($vAmount > 0) {
                            $breakdown[] = [
                                'label' => "Voucher ({$voucherApp->voucherDefinition?->code})", 
                                'amount' => -$vAmount
                            ];
                            $studentTotal -= $vAmount;
                         } else {
                            // Informational Voucher
                            $breakdown[] = [
                                'label' => "Voucher Info ({$voucherApp->voucherDefinition?->code})", 
                                'amount' => 0
                            ];
                         }
                     }
                 }
            }


            // 3. Manual
            if (!empty($params['custom_amount'])) {
                $breakdown[] = ['label' => 'Custom Fee', 'amount' => (float) $params['custom_amount']];
                $studentTotal += (float) $params['custom_amount'];
                $grossAmount += (float) $params['custom_amount'];
            }
            
            // Determine Invoice Eligibility
            $isTuitionEligible = ($student->status === 'intake_course' && in_array(FinanceCharge::TYPE_TUITION_TERM, $chargeTypes) && $this->getTuitionFee($student, $semesterId) !== null);
            $shouldGenInvoice = ($grossAmount > 0) || $hasRetake || $hasExistingInvoice || $isTuitionEligible;

            $willCreateInvoice = $shouldGenInvoice && !$hasExistingInvoice;

            if ($willCreateInvoice || ($hasExistingInvoice && ($studentTotal != 0 || !empty($breakdown)))) {
                 if ($willCreateInvoice) {
                     // Start new invoice process
                     $newChargesCount++; 
                 } else {
                     // Update existing
                     if ($studentTotal != 0) $newChargesCount++;
                 }
                 
                 $totalAmount += $studentTotal;
            } else {
                if ($hasExistingCharge || $hasExistingInvoice) {
                    $skipCount++; // Skipped because done
                }
                // Else: Skipped because not eligible (0$ and no retake)
            }

            $previewItems[] = [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'status' => $student->status,
                'has_existing_charge' => $hasExistingCharge,
                'estimated_amount' => $studentTotal,
                'warning' => $hasExistingCharge ? 'Existing charges found' : ($shouldGenInvoice ? null : 'No eligible charges (0đ)'),
                'breakdown' => $breakdown,
                'will_create_invoice' => $willCreateInvoice,
            ];
        }

        // Filter out no-action items
        $filteredItems = array_filter($previewItems, function($item) {
             return $item['will_create_invoice'] || $item['has_existing_charge'];
        });
        $filteredItems = array_values($filteredItems);

        return [
            'students' => array_slice($filteredItems, 0, 50),
            'total_students' => count($filteredItems),
            'new_charges_count' => $newChargesCount,
            'skip_count' => $skipCount,
            'total_amount' => $totalAmount,
            'warnings' => $warnings,
        ];
    }

    private function getTuitionFee(Student $student, int $semesterId): ?float
    {
        $intakeMajor = $student->intake_major;
        
        // Use intake_major as the start milestone. 
        if (!$intakeMajor || $semesterId < $intakeMajor) {
            return null;
        }

        // Count existing tuition installments from semester >= intake_major
        $paidInstallments = FinanceCharge::where('student_id', $student->id)
            ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
            ->where('semester_id', '>=', $intakeMajor)
            ->where('semester_id', '<', $semesterId)
            ->active()
            ->count();
            
        $nextTermNumber = $paidInstallments + 1;

        // Find plan based on student's university intake cohort
        $plan = \App\Models\TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $student->intake_semester_id)
            ->first();

        if (!$plan) return null;

        $term = \App\Models\TuitionPlanTerm::where('tuition_plan_id', $plan->id)
            ->where('term_number', $nextTermNumber)
            ->first();

        return $term ? (float) $term->amount : null;
    }

    private function checkChargeExists(Student $student, int $semesterId, string $type): bool
    {
        return FinanceCharge::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', $type)
            ->active()
            ->exists();
    }
}
