<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\Egc\SyncEgcBlockResultsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BackfillEgcBlocks extends Command
{
    protected $signature = 'egc:backfill-blocks
                            {--dry-run : Preview without writing}
                            {--reset : Delete and recreate all blocks (fixes wrong data)}
                            {--fall2025-semester= : ID of FALL2025 semester}
                            {--spring2026-semester= : ID of SPRING2026 semester}';

    protected $description = 'Backfill egc_blocks from EGC registrations and active EGC charges for EGC and transitioned students';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isReset = (bool) $this->option('reset');
        $fall2025SemId = $this->option('fall2025-semester') ? (int) $this->option('fall2025-semester') : null;
        $spring2026SemId = $this->option('spring2026-semester') ? (int) $this->option('spring2026-semester') : null;

        $this->info('Starting EGC block backfill...');
        if ($isDryRun) {
            $this->warn('[DRY RUN] No data will be written.');
        }

        $studentIdsWithEgcRegistrations = DB::table('course_registrations')
            ->join('course_offerings', 'course_registrations.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('units.unit_type', 'egc')
            ->distinct()
            ->pluck('course_registrations.student_id');

        $studentIdsWithActiveEgcCharges = FinanceCharge::query()
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->distinct()
            ->pluck('student_id');

        $students = Student::query()
            ->whereIn('id', $studentIdsWithEgcRegistrations->merge($studentIdsWithActiveEgcCharges)->unique()->values())
            ->get();

        $this->info("Found {$students->count()} students with EGC history or active EGC charges.");

        if ($isReset && ! $isDryRun) {
            $this->warn('Resetting existing egc_blocks...');
            EgcBlock::whereIn('student_id', $students->pluck('id'))->delete();
        }

        $stats = [
            'created' => 0,
            'skipped' => 0,
            'linked_charges' => 0,
            'reconciled_links' => 0,
            'created_from_charge_fallback' => 0,
            'synced_results' => 0,
            'charges_without_registrations' => [],
            'registration_charge_mismatches' => [],
            'charges_without_level_hint' => [],
        ];

        foreach ($students as $student) {
            $this->backfillStudent($student, $isDryRun, $stats);
        }

        $this->info("Blocks created: {$stats['created']}, skipped: {$stats['skipped']}, charges linked: {$stats['linked_charges']}, reconciled existing links: {$stats['reconciled_links']}, created from charge fallback: {$stats['created_from_charge_fallback']}");

        if (! $isDryRun) {
            $this->syncResults($fall2025SemId, $stats);

            if ($spring2026SemId !== null && $fall2025SemId !== null) {
                $this->applyRetakeForSpring2026($fall2025SemId, $spring2026SemId);
            }
        }

        $this->verifyOutput();
        $this->reportChargesWithoutRegistrations($stats['charges_without_registrations']);
        $this->reportRegistrationChargeMismatches($stats['registration_charge_mismatches']);
        $this->reportChargesWithoutLevelHint($stats['charges_without_level_hint']);
        $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    /**
     * Create egc_blocks for a student from actual EGC registrations.
     *
     * Rules:
     * - Level comes from course_registration → unit.level when available
     * - Existing egc_blocks missing finance_charge_id get repaired in place
     * - Active charges without matching registrations are reported, not turned into new blocks
     * - Max 2 blocks per semester
     * - Same level twice in a semester → second block is is_retake=true
     */
    private function backfillStudent(Student $student, bool $isDryRun, array &$stats): void
    {
        $studentId = $student->id;
        $registrations = DB::table('course_registrations')
            ->join('course_offerings', 'course_registrations.course_offering_id', '=', 'course_offerings.id')
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_registrations.student_id', $studentId)
            ->where('units.unit_type', 'egc')
            ->orderBy('course_offerings.semester_id', 'asc')
            ->orderBy('course_registrations.id', 'asc')
            ->select(['course_registrations.id as reg_id', 'course_offerings.semester_id', 'units.level as level_number'])
            ->get();

        $chargesBySemester = FinanceCharge::where('student_id', $studentId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->orderBy('semester_id')
            ->orderBy('id')
            ->get()
            ->groupBy('semester_id');

        $registrationSemesters = $registrations->groupBy('semester_id');
        $allSemesterIds = $registrationSemesters->keys()
            ->merge($chargesBySemester->keys())
            ->unique()
            ->sort()
            ->values();

        foreach ($allSemesterIds as $semesterId) {
            $sortedRegs = $registrationSemesters->get($semesterId, collect())->sortBy('reg_id')->values()->take(2);
            $charges = $chargesBySemester->get($semesterId, collect())->values();
            $sourceRows = $this->buildBackfillSourceRows($student, (int) $semesterId, $sortedRegs, $charges, $stats);

            foreach ($sourceRows as $i => $sourceRow) {
                $blockNumber = $i + 1;
                $levelNumber = (int) $sourceRow['level_number'];
                $charge = $sourceRow['charge'];
                $isRetake = $i > 0 && (int) ($sourceRows[$i - 1]['level_number'] ?? -1) === $levelNumber;
                $existingBlock = EgcBlock::where('student_id', $studentId)
                    ->where('semester_id', $semesterId)
                    ->where('block_number', $blockNumber)
                    ->first();

                if ($existingBlock !== null) {
                    $stats['skipped']++;

                    if ($charge === null) {
                        $charge = $isDryRun
                            ? null
                            : $this->createMissingChargeForBlock($student, (int) $semesterId, $levelNumber);
                    }

                    if ($charge !== null && $existingBlock->finance_charge_id !== $charge->id) {
                        if (! $isDryRun) {
                            $existingBlock->update(['finance_charge_id' => $charge->id]);
                            $this->syncChargeDescriptionToLevel($charge, $levelNumber);
                        }

                        $stats['reconciled_links']++;
                    }

                    continue;
                }

                if ($charge === null && ! $isDryRun) {
                    $charge = $this->createMissingChargeForBlock($student, (int) $semesterId, $levelNumber);
                }

                if (! $isDryRun) {
                    EgcBlock::create([
                        'student_id' => $studentId,
                        'semester_id' => $semesterId,
                        'block_number' => $blockNumber,
                        'level_number' => $levelNumber,
                        'result' => EgcBlock::RESULT_PENDING,
                        'is_retake' => $isRetake,
                        'finance_charge_id' => $charge?->id,
                    ]);

                    if ($charge !== null) {
                        $this->syncChargeDescriptionToLevel($charge, $levelNumber);
                    }
                }

                $stats['created']++;
                if ($charge) {
                    $stats['linked_charges']++;
                }
                if (($sourceRow['source'] ?? 'registration') === 'charge_fallback') {
                    $stats['created_from_charge_fallback']++;
                }
            }
        }
    }

    private function syncResults(int|null $fall2025SemId, array &$stats): void
    {
        $semesterIds = DB::table('egc_blocks')->distinct()->pluck('semester_id')->toArray();

        foreach ($semesterIds as $semId) {
            $result = SyncEgcBlockResultsAction::run((int) $semId);
            $stats['synced_results'] += $result['synced'];

            // FALL2025 predates retake policy — force is_retake=false after sync
            if ($fall2025SemId !== null && (int) $semId === $fall2025SemId) {
                EgcBlock::where('semester_id', $semId)->update(['is_retake' => false]);
            }
        }

        $this->info("Results synced: {$stats['synced_results']}");
    }

    private function applyRetakeForSpring2026(int $fall2025SemId, int $spring2026SemId): void
    {
        $this->info('Applying is_retake flags for SPRING2026...');
        $retakeCount = 0;

        foreach (EgcBlock::where('semester_id', $spring2026SemId)->get() as $block) {
            $hasFallFail = EgcBlock::where('student_id', $block->student_id)
                ->where('semester_id', $fall2025SemId)
                ->where('level_number', $block->level_number)
                ->where('result', EgcBlock::RESULT_FAIL)
                ->where('attendance_rate', '>=', 80)
                ->exists();

            if ($hasFallFail) {
                $block->update(['is_retake' => true]);
                $retakeCount++;
            }
        }

        $this->info("SPRING2026 blocks flagged as is_retake: {$retakeCount}");
    }

    private function verifyOutput(): void
    {
        $this->newLine();
        $this->info('=== Backfill Verification ===');
        $this->line('Total egc_blocks:              '.EgcBlock::count());
        $this->line('Blocks with no finance_charge: '.EgcBlock::whereNull('finance_charge_id')->count());
        $this->line('Blocks with is_retake = true:  '.EgcBlock::where('is_retake', true)->count());
        $this->line('Blocks with result = fail:     '.EgcBlock::where('result', EgcBlock::RESULT_FAIL)->count());
    }

    /**
     * @param  array<int, array{student_code: string, student_name: string, semester_id: int, charge_ids: array<int>, charge_descriptions: array<int, string>}>  $rows
     */
    private function reportChargesWithoutRegistrations(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->warn('=== Charges With No course_registration ===');

        foreach ($rows as $row) {
            $this->line(sprintf(
                '%s - %s | semester_id=%d | charge_ids=%s | descriptions=%s',
                $row['student_code'],
                $row['student_name'],
                $row['semester_id'],
                implode(',', $row['charge_ids']),
                implode(' || ', $row['charge_descriptions'])
            ));
        }
    }

    /**
     * @param  Collection<int, object>  $sortedRegs
     * @param  Collection<int, FinanceCharge>  $charges
     * @return array<int, array{source: string, level_number: int, charge: FinanceCharge|null}>
     */
    private function buildBackfillSourceRows(Student $student, int $semesterId, Collection $sortedRegs, Collection $charges, array &$stats): array
    {
        $rows = [];
        $remainingCharges = $charges->values();

        if ($sortedRegs->isEmpty() && $charges->isNotEmpty()) {
            $stats['charges_without_registrations'][] = [
                'student_code' => $student->student_id,
                'student_name' => $student->full_name,
                'semester_id' => $semesterId,
                'charge_ids' => $charges->pluck('id')->all(),
                'charge_descriptions' => $charges->pluck('description')->all(),
            ];
        }

        if ($sortedRegs->count() !== $charges->count()) {
            $stats['registration_charge_mismatches'][] = [
                'student_code' => $student->student_id,
                'student_name' => $student->full_name,
                'semester_id' => $semesterId,
                'registration_count' => $sortedRegs->count(),
                'charge_count' => $charges->count(),
            ];
        }

        foreach ($sortedRegs as $reg) {
            $matchedChargeIds = [];
            $levelNumber = (int) $reg->level_number;
            $charge = $this->resolveChargeForBlock($remainingCharges, $matchedChargeIds, $levelNumber);

            if ($charge !== null) {
                $remainingCharges = $remainingCharges
                    ->reject(fn (FinanceCharge $candidate) => $candidate->id === $charge->id)
                    ->values();
            }

            $rows[] = [
                'source' => 'registration',
                'level_number' => $levelNumber,
                'charge' => $charge,
            ];
        }

        $existingNullChargeBlocks = EgcBlock::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->whereNull('finance_charge_id')
            ->orderBy('block_number')
            ->get();

        foreach ($existingNullChargeBlocks as $block) {
            if (count($rows) >= 2) {
                break;
            }

            $rows[] = [
                'source' => 'existing_block_missing_charge',
                'level_number' => (int) $block->level_number,
                'charge' => null,
            ];
        }

        return array_slice($rows, 0, 2);
    }

    private function createMissingChargeForBlock(Student $student, int $semesterId, int $levelNumber): FinanceCharge
    {
        $existingCharge = FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('description', "EGC Level {$levelNumber} Fee")
            ->first();

        if ($existingCharge !== null) {
            return $existingCharge;
        }

        $invoice = StudentInvoice::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'semester_id' => $semesterId,
            ],
            [
                'invoice_number' => 'INV-BF-'.time().'-'.$student->student_id.'-'.$semesterId,
                'status' => 'draft',
                'due_date' => now()->addDays(30),
            ],
        );

        $charge = FinanceCharge::query()->create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => 15_000_000,
            'description' => "EGC Level {$levelNumber} Fee",
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
            'created_by_user_id' => auth()->id(),
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $charge->amount,
            'description_snapshot' => $charge->description,
            'status' => 'active',
        ]);

        $invoice->recalculateTotals();

        return $charge;
    }

    /**
     * @param  array<int, array{student_code: string, student_name: string, semester_id: int, registration_count: int, charge_count: int}>  $rows
     */
    private function reportRegistrationChargeMismatches(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->warn('=== Registration / Charge Count Mismatches ===');

        foreach ($rows as $row) {
            $this->line(sprintf(
                '%s - %s | semester_id=%d | registrations=%d | charges=%d',
                $row['student_code'],
                $row['student_name'],
                $row['semester_id'],
                $row['registration_count'],
                $row['charge_count'],
            ));
        }
    }

    /**
     * @param  array<int, array{student_code: string, student_name: string, semester_id: int, charge_id: int, charge_description: string|null}>  $rows
     */
    private function reportChargesWithoutLevelHint(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->warn('=== Charges Without Level Hint ===');

        foreach ($rows as $row) {
            $this->line(sprintf(
                '%s - %s | semester_id=%d | charge_id=%d | description=%s',
                $row['student_code'],
                $row['student_name'],
                $row['semester_id'],
                $row['charge_id'],
                $row['charge_description'] ?? '(null)',
            ));
        }
    }

    /**
     * Prefer an unlinked charge whose current description already matches the real level.
     * If none exists, fall back to the original positional assignment.
     *
     * @param  Collection<int, FinanceCharge>  $charges
     * @param  array<int>  $matchedChargeIds
     */
    private function resolveChargeForBlock(Collection $charges, array &$matchedChargeIds, int $levelNumber): ?FinanceCharge
    {
        $charge = $charges
            ->first(fn (FinanceCharge $candidate) => ! in_array($candidate->id, $matchedChargeIds, true)
                && str_contains($candidate->description, "Level {$levelNumber}"));

        if ($charge === null) {
            $charge = $charges
                ->reject(fn (FinanceCharge $candidate) => in_array($candidate->id, $matchedChargeIds, true))
                ->values()
                ->first();
        }

        if ($charge !== null) {
            $matchedChargeIds[] = $charge->id;
        }

        return $charge;
    }

    private function syncChargeDescriptionToLevel(FinanceCharge $charge, int $levelNumber): void
    {
        $expectedDescription = "EGC Level {$levelNumber} Fee";

        if ($charge->description === $expectedDescription) {
            return;
        }

        $charge->update(['description' => $expectedDescription]);

        InvoiceLine::where('charge_id', $charge->id)->update([
            'description_snapshot' => $expectedDescription,
        ]);
    }
}
