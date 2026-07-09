<?php

namespace App\Console\Commands;

use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\VoucherApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillStudentFees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:backfill-fees {--semester=1 : The semester ID to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill student invoices and finance charges for a specific semester.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $semesterId = (int) $this->option('semester');
        $this->info("Starting backfill for Semester ID: $semesterId");

        // Stats
        $stats = [
            'processed' => 0,
            'invoices_created' => 0,
            'charges_created' => 0,
            'vouchers_applied' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        DB::beginTransaction();

        try {
            // 1. Fetch Students
            // Case 1: Major Cohort (intake_major = 1, intake_gc IS NULL)
            // Case 2: EGC Cohort (intake_gc = 1)
            // We'll fetch all matching students and process them

            $students = Student::query()
                ->where(function ($query) use ($semesterId) {
                    $query->where(function ($q) use ($semesterId) {
                        $q->where('intake_major', $semesterId)
                            ->whereNull('intake_gc');
                    })
                        ->orWhere(function ($q) use ($semesterId) {
                            $q->where('intake_gc', $semesterId);
                        });
                })
                // ->whereNotIn('status', Student::BLOCKED_STATUSES) // Optional: fail-safe
                ->get();

            $this->info('Found '.$students->count().' eligible students.');
            $bar = $this->output->createProgressBar($students->count());
            $bar->start();

            foreach ($students as $student) {
                try {
                    $isEgcCohort = $student->intake_gc == $semesterId;

                    // 2. Ensure Invoice Exists
                    $invoice = StudentInvoice::firstOrCreate(
                        [
                            'student_id' => $student->id,
                            'semester_id' => $semesterId,
                        ],
                        [
                            'invoice_number' => 'INV-BF-'.time().'-'.$student->student_id.'-'.$semesterId,
                            'due_date' => now()->addDays(30),
                            'opened_at' => now(),
                            'status' => 'draft', // or 'issued' if backfilling history? Let's stick to draft/issued
                        ]
                    );

                    if ($invoice->wasRecentlyCreated) {
                        $stats['invoices_created']++;
                    }

                    $chargesToLink = [];

                    // 3. Generate Charges

                    // === CASE 2: EGC COHORT ===
                    if ($isEgcCohort) {
                        $startLevel = $student->gc_starting_level ?? 0;
                        $totalLevels = $student->gc_total_levels ?? 6;

                        // Charge for Current Level + Next Level (max 2 levels per semester)
                        // Logic: Semester 1 roughly maps to starting levels.
                        // If we are strict backfilling 'Semester 1' logic:
                        // Level A = startLevel
                        // Level B = startLevel + 1 (if <= totalLevels)

                        $levelsToCharge = [];
                        $levelsToCharge[] = $startLevel;
                        if ($startLevel + 1 < $totalLevels) { // Sanity check
                            $levelsToCharge[] = $startLevel + 1;
                        }

                        foreach ($levelsToCharge as $level) {
                            $unitCode = "EGC-L{$level}";
                            $amount = 15000000; // Hardcoded per user requirement

                            $charge = $this->createChargeIfNotExists(
                                $student,
                                $semesterId,
                                FinanceCharge::TYPE_EGC_LEVEL_FEE,
                                $amount,
                                "EGC Level {$level} Fee",
                                "Level {$level} Fee" // Key for idempotency (matches description)
                            );

                            if ($charge) {
                                $chargesToLink[] = $charge;
                            }
                        }
                    }
                    // === CASE 1: MAJOR COHORT ===
                    else {
                        // Tuition Fee from Plan
                        $amount = $this->getTuitionFee($student, $semesterId);

                        if ($amount > 0) {
                            $charge = $this->createChargeIfNotExists(
                                $student,
                                $semesterId,
                                FinanceCharge::TYPE_TUITION_TERM,
                                $amount,
                                'Major Tuition (Installment 1)'
                            );
                            if ($charge) {
                                $chargesToLink[] = $charge;

                                // Scholarship (Only for Major Tuition ?)
                                // Logic: If student has scholarship, apply credit
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
                                            $schCharge = $this->createChargeIfNotExists(
                                                $student,
                                                $semesterId,
                                                FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
                                                -$discount,
                                                "Scholarship Credit ({$scholarshipDef->code})",
                                                'SCHOLARSHIP'
                                            );
                                            if ($schCharge) {
                                                $chargesToLink[] = $schCharge;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // 4. Voucher Application (Common)
                    $voucherApp = VoucherApplication::with('voucherDefinition')
                        ->where('student_id', $student->id)
                        ->whereNull('invoice_id')
                        ->where(function ($q) use ($semesterId) {
                            $q->where('semester_id', $semesterId)
                                ->orWhereNull('semester_id');
                        })
                        ->first();

                    if ($voucherApp && $voucherApp->voucherDefinition) {
                        $def = $voucherApp->voucherDefinition;

                        if ($def->voucher_type === 'discount') {
                            $vAmount = (float) $voucherApp->discount_amount;

                            // Fallback calculation if amount is missing
                            if ($vAmount <= 0) {
                                if ($def->discount_type === 'fixed_amount') {
                                    $vAmount = (float) $def->discount_value;
                                } elseif ($def->discount_type === 'percentage') {
                                    $baseAmount = 0;
                                    foreach ($chargesToLink as $c) {
                                        if (in_array($c->charge_type, [FinanceCharge::TYPE_TUITION_TERM, FinanceCharge::TYPE_EGC_LEVEL_FEE])) {
                                            $baseAmount += $c->amount;
                                        }
                                    }
                                    $vAmount = ($baseAmount * $def->discount_value) / 100;

                                    if ($def->max_discount_amount && $vAmount > $def->max_discount_amount) {
                                        $vAmount = (float) $def->max_discount_amount;
                                    }
                                }
                            }

                            if ($vAmount > 0) {
                                $vCharge = $this->createChargeIfNotExists(
                                    $student,
                                    $semesterId,
                                    FinanceCharge::TYPE_VOUCHER_CREDIT,
                                    -$vAmount,
                                    "Voucher Applied ({$def->code})",
                                    "VOUCHER-{$voucherApp->id}",
                                    VoucherApplication::class,
                                    $voucherApp->id
                                );

                                if ($vCharge) {
                                    $chargesToLink[] = $vCharge;

                                    $voucherApp->update([
                                        'invoice_id' => $invoice->id,
                                        'finance_charge_id' => $vCharge->id,
                                        'discount_amount' => $vAmount, // Save calculated amount
                                        'applied_at' => now(),
                                        'status' => 'applied',
                                    ]);
                                    $stats['vouchers_applied']++;
                                }
                            }
                        } elseif ($def->voucher_type === 'informational') {
                            // Info only voucher: No Finance Charge created
                            $voucherApp->update([
                                'invoice_id' => $invoice->id,
                                'applied_at' => now(),
                                'status' => 'applied',
                            ]);
                            $stats['vouchers_applied']++;
                        }
                    }

                    // 5. Link Charges to Invoice
                    foreach ($chargesToLink as $c) {
                        if ($c->wasRecentlyCreated) {
                            $stats['charges_created']++;
                        }

                        InvoiceLine::firstOrCreate([
                            'invoice_id' => $invoice->id,
                            'charge_id' => $c->id,
                        ], [
                            'amount_snapshot' => $c->amount,
                            'description_snapshot' => $c->description,
                        ]);
                    }

                    $stats['processed']++;
                    $bar->advance();
                } catch (\Exception $e) {
                    Log::error("Backfill Error Student {$student->id}: ".$e->getMessage());
                    $stats['errors']++;
                    $this->error("Error processing student {$student->student_id}: {$e->getMessage()}");
                }
            }

            $bar->finish();
            $this->newLine();

            DB::commit();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Students Processed', $stats['processed']],
                    ['Invoices Created', $stats['invoices_created']],
                    ['Charges Created', $stats['charges_created']],
                    ['Vouchers Applied', $stats['vouchers_applied']],
                    ['Errors', $stats['errors']],
                ]
            );
            $this->info('Backfill completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Critical Error: '.$e->getMessage());
            $this->error('Transaction rolled back.');

            return 1;
        }

        return 0;
    }

    private function getTuitionFee(Student $student, int $semesterId): float
    {
        $intakeSemesterId = $student->intake_major ?? $student->intake_gc ?? $student->intake;

        if (! $student->curriculum_version_id || ! $intakeSemesterId) {
            return 0;
        }

        $plan = TuitionPlan::where('curriculum_version_id', $student->curriculum_version_id)
            ->where('intake_semester_id', $intakeSemesterId)
            ->first();

        if (! $plan) {
            return 0;
        }

        // Calculate Term Number based on semester progression
        $intakeSemester = Semester::find($intakeSemesterId);
        $targetSemester = Semester::find($semesterId);

        if (! $intakeSemester || ! $targetSemester) {
            return 0;
        }

        // If target is before intake, no fee
        if ($targetSemester->start_date < $intakeSemester->start_date) {
            return 0;
        }

        // Count semesters started between intake and target (inclusive)
        $termNumber = Semester::where('start_date', '>=', $intakeSemester->start_date)
            ->where('start_date', '<=', $targetSemester->start_date)
            ->count();
        $term = TuitionPlanTerm::where('tuition_plan_id', $plan->id)
            ->where('term_number', $termNumber)
            ->first();

        return $term ? (float) $term->amount : 0;
    }

    private function createChargeIfNotExists(
        Student $student,
        int $semesterId,
        string $type,
        float $amount,
        string $description,
        string $dedupKey = '',
        ?string $sourceType = null,
        ?int $sourceId = null
    ): ?FinanceCharge {
        // Idempotency: distinct by type + semester + student + amount?
        // Or if dedupKey is provided, use it?
        // Let's use Source for dedupKey if possible, but Source is constrained morph.
        // We will just check existence by Type + Semester + Student (+ approx amount)

        $query = FinanceCharge::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', $type);

        // If EGC, we might have multiple charges of same type (Level 1, Level 2).
        // So we need to distinguish them. Description check? Or Source?
        if ($type === FinanceCharge::TYPE_EGC_LEVEL_FEE && $dedupKey) {
            $query->where('description', 'like', "%$dedupKey%");
        }

        // If Voucher/Scholarship, check source to ensure unique application
        if ($sourceType && $sourceId) {
            $query->where('source_type', $sourceType)
                ->where('source_id', $sourceId);
        }

        if ($query->exists()) {
            return $query->first(); // Already exists
        }

        return FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'charge_type' => $type,
            'amount' => $amount,
            'description' => $description,
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
            'created_by_user_id' => 1, // System admin or null
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
    }
}
