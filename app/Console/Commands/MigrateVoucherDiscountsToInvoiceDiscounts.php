<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DiscountAllocation;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\VoucherApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateVoucherDiscountsToInvoiceDiscounts extends Command
{
    private bool $hasStatusColumn = false;

    protected $signature = 'finance:migrate-voucher-discounts
        {--dry-run : Preview changes without writing to the database}
        {--force : Skip confirmation before applying changes}
        {--student= : Limit to one student database id or student code}';

    protected $description = 'Migrate applied voucher applications into invoice_discounts and backfill discount_allocations using oldest_line_first';

    public function handle(): int
    {
        $this->hasStatusColumn = Schema::hasColumn('invoice_discounts', 'status');

        $studentId = $this->resolveStudentFilter();

        if ($studentId === false) {
            return self::FAILURE;
        }

        $candidates = $this->buildCandidates($studentId);

        if ($candidates->isEmpty()) {
            $this->info('No applied voucher applications matched the migration criteria.');

            return self::SUCCESS;
        }

        $this->warn('This command only migrates voucher headers into invoice_discounts.');
        $this->line('  - no payment allocation migration');
        $this->line('  - no payment_applications backfill');
        $this->line('  - discount_allocations backfill uses oldest_line_first');
        $this->newLine();

        $this->displaySummary($candidates);

        $allocationPlan = $this->buildAllocationPlan($studentId, $candidates);
        $this->displayAllocationSummary($allocationPlan);

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - no database changes were made.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply voucher discount migration to invoice_discounts?', false)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($candidates, $studentId): void {
            foreach ($candidates as $candidate) {
                if ($candidate['action'] === 'skip') {
                    continue;
                }

                if ($candidate['action'] === 'update') {
                    $payload = [
                        'discount_source' => $candidate['discount_source'],
                        'description' => $candidate['description'],
                        'amount' => $candidate['amount'],
                        'updated_at' => now(),
                    ];

                    if ($this->hasStatusColumn) {
                        $payload['status'] = 'active';
                    }

                    DB::table('invoice_discounts')
                        ->where('id', $candidate['existing_id'])
                        ->update($payload);

                    continue;
                }

                $payload = [
                    'invoice_id' => $candidate['invoice_id'],
                    'discount_type' => 'voucher',
                    'discount_source' => $candidate['discount_source'],
                    'description' => $candidate['description'],
                    'amount' => $candidate['amount'],
                    'reference_id' => $candidate['reference_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($this->hasStatusColumn) {
                    $payload['status'] = 'active';
                }

                DB::table('invoice_discounts')->insert($payload);
            }

            $allocationPlan = $this->buildAllocationPlan($studentId, collect());

            foreach ($allocationPlan as $allocationCandidate) {
                if ($allocationCandidate['action'] !== 'create') {
                    continue;
                }

                foreach ($allocationCandidate['allocations'] as $allocation) {
                    DiscountAllocation::query()->create([
                        'invoice_discount_id' => $allocationCandidate['invoice_discount_id'],
                        'invoice_line_id' => $allocation['invoice_line_id'],
                        'amount' => $allocation['amount'],
                        'entry_type' => 'allocation',
                        'source_ref_type' => InvoiceDiscount::class,
                        'source_ref_id' => $allocationCandidate['invoice_discount_id'],
                        'allocation_rule' => 'oldest_line_first',
                    ]);
                }
            }
        });

        $this->info('Voucher discount migration completed.');

        return self::SUCCESS;
    }

    private function resolveStudentFilter(): int|false|null
    {
        $studentOption = $this->option('student');

        if ($studentOption === null || $studentOption === '') {
            return null;
        }

        $student = Student::query()
            ->when(is_numeric($studentOption), function ($query) use ($studentOption) {
                $query->where('id', (int) $studentOption);
            }, function ($query) use ($studentOption) {
                $query->where('student_id', $studentOption);
            })
            ->first();

        if (! $student) {
            $this->error("Student filter '{$studentOption}' did not match any student.");

            return false;
        }

        return (int) $student->id;
    }

    private function buildCandidates(?int $studentId): Collection
    {
        $applications = VoucherApplication::query()
            ->with(['student:id,student_id,full_name', 'invoice:id,invoice_number,student_id', 'voucherDefinition:id,code,name'])
            ->whereNotNull('invoice_id')
            ->where('status', 'applied')
            ->whereNotNull('discount_amount')
            ->where('discount_amount', '>', 0)
            ->when($studentId !== null, fn ($query) => $query->where('student_id', $studentId))
            ->orderBy('id')
            ->get();

        return $applications->map(function (VoucherApplication $application): array {
            $invoice = $application->invoice;
            $student = $application->student;
            $voucher = $application->voucherDefinition;

            $discountSource = VoucherApplication::class;
            $description = $voucher && $voucher->code
                ? "Voucher Applied ({$voucher->code})"
                : 'Voucher Applied';
            $amount = abs((float) $application->discount_amount);

            $existing = DB::table('invoice_discounts')
                ->where('invoice_id', $application->invoice_id)
                ->where('discount_type', 'voucher')
                ->where('reference_id', $application->id)
                ->first();

            $action = 'create';

            if ($existing) {
                $isSame = $existing->discount_source === $discountSource
                    && $existing->description === $description
                    && (float) $existing->amount === $amount
                    && (! $this->hasStatusColumn || ($existing->status ?? 'active') === 'active');

                $action = $isSame ? 'skip' : 'update';
            }

            return [
                'voucher_application_id' => (int) $application->id,
                'student_code' => $student?->student_id,
                'student_name' => $student?->full_name,
                'invoice_id' => (int) $application->invoice_id,
                'invoice_number' => $invoice?->invoice_number,
                'voucher_code' => $voucher?->code,
                'amount' => $amount,
                'discount_source' => $discountSource,
                'description' => $description,
                'reference_id' => (int) $application->id,
                'existing_id' => $existing?->id,
                'action' => $action,
            ];
        });
    }

    private function displaySummary(Collection $candidates): void
    {
        $grouped = $candidates->groupBy('action');

        $this->info('Voucher discount migration preview');
        $this->line('  - candidates: '.$candidates->count());
        $this->line('  - creates: '.$grouped->get('create', collect())->count());
        $this->line('  - updates: '.$grouped->get('update', collect())->count());
        $this->line('  - skips: '.$grouped->get('skip', collect())->count());
        $this->newLine();

        $this->table(
            ['Action', 'VoucherApp', 'Student', 'Invoice', 'Voucher', 'Amount'],
            $candidates->map(fn (array $candidate): array => [
                $candidate['action'],
                $candidate['voucher_application_id'],
                trim(($candidate['student_code'] ?? '').' '.($candidate['student_name'] ?? '')),
                ($candidate['invoice_number'] ?? '#'.$candidate['invoice_id']),
                $candidate['voucher_code'] ?? 'n/a',
                number_format($candidate['amount'], 0),
            ])->all(),
        );
    }

    private function buildAllocationPlan(?int $studentId, Collection $candidates): Collection
    {
        $discounts = InvoiceDiscount::query()
            ->with(['invoice.student', 'invoice.invoiceLines.charge', 'allocations'])
            ->where('discount_type', 'voucher')
            ->when($studentId !== null, function ($query) use ($studentId) {
                $query->whereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('student_id', $studentId));
            })
            ->get()
            ->keyBy(fn (InvoiceDiscount $discount) => $discount->invoice_id.'-'.$discount->reference_id);

        $plannedDiscounts = $candidates->isEmpty()
            ? $discounts->values()
            : $candidates->map(function (array $candidate) use ($discounts) {
                $key = $candidate['invoice_id'].'-'.$candidate['reference_id'];
                $discount = $discounts->get($key);

                if ($discount) {
                    return $discount;
                }

                $invoice = \App\Models\StudentInvoice::query()
                    ->with(['student', 'invoiceLines.charge'])
                    ->find($candidate['invoice_id']);

                if (! $invoice) {
                    return null;
                }

                $synthetic = new InvoiceDiscount([
                    'id' => 0,
                    'invoice_id' => $candidate['invoice_id'],
                    'discount_type' => 'voucher',
                    'discount_source' => $candidate['discount_source'],
                    'description' => $candidate['description'],
                    'amount' => $candidate['amount'],
                    'reference_id' => $candidate['reference_id'],
                ]);
                $synthetic->setRelation('invoice', $invoice);
                $synthetic->setRelation('allocations', collect());

                return $synthetic;
            })->filter()->values();

        return $plannedDiscounts->map(function (InvoiceDiscount $discount): array {
            $existingNet = max(0, (float) $discount->allocations->sum('amount'));

            if ($existingNet > 0) {
                return [
                    'invoice_discount_id' => $discount->id,
                    'invoice_number' => $discount->invoice?->invoice_number,
                    'student_code' => $discount->invoice?->student?->student_id,
                    'amount' => (float) $discount->amount,
                    'action' => 'skip_existing',
                    'allocations' => [],
                ];
            }

            $eligibleLines = $discount->invoice?->invoiceLines
                ->filter(function (InvoiceLine $line) {
                    return ($line->status ?? 'active') === 'active'
                        && (float) $line->amount_snapshot > 0
                        && ($line->charge === null || $line->charge->status === FinanceCharge::STATUS_ACTIVE);
                })
                ->sortBy(fn (InvoiceLine $line) => [
                    optional($line->created_at)?->getTimestamp() ?? 0,
                    $line->id,
                ])
                ->values() ?? collect();

            if ($eligibleLines->isEmpty()) {
                return [
                    'invoice_discount_id' => $discount->id,
                    'invoice_number' => $discount->invoice?->invoice_number,
                    'student_code' => $discount->invoice?->student?->student_id,
                    'amount' => (float) $discount->amount,
                    'action' => 'skip_no_lines',
                    'allocations' => [],
                ];
            }

            $remaining = (float) $discount->amount;
            $allocations = [];

            foreach ($eligibleLines as $line) {
                if ($remaining <= 0) {
                    break;
                }

                $allocateAmount = min($remaining, max(0, (float) $line->amount_snapshot));

                if ($allocateAmount <= 0) {
                    continue;
                }

                $allocations[] = [
                    'invoice_line_id' => $line->id,
                    'amount' => $allocateAmount,
                ];

                $remaining -= $allocateAmount;
            }

            return [
                'invoice_discount_id' => $discount->id,
                'invoice_number' => $discount->invoice?->invoice_number,
                'student_code' => $discount->invoice?->student?->student_id,
                'amount' => (float) $discount->amount,
                'action' => $allocations === [] ? 'skip_no_capacity' : 'create',
                'allocations' => $allocations,
                'unallocated_amount' => max(0, $remaining),
            ];
        });
    }

    private function displayAllocationSummary(Collection $allocationPlan): void
    {
        $grouped = $allocationPlan->groupBy('action');

        $this->info('Discount allocation backfill preview');
        $this->line('  - creates: '.$grouped->get('create', collect())->count());
        $this->line('  - skips (existing): '.$grouped->get('skip_existing', collect())->count());
        $this->line('  - skips (no lines): '.$grouped->get('skip_no_lines', collect())->count());
        $this->line('  - skips (no capacity): '.$grouped->get('skip_no_capacity', collect())->count());
        $this->newLine();

        $this->table(
            ['Action', 'Discount', 'Student', 'Invoice', 'Amount', 'Allocations'],
            $allocationPlan->map(fn (array $candidate): array => [
                $candidate['action'],
                $candidate['invoice_discount_id'],
                $candidate['student_code'] ?? 'n/a',
                $candidate['invoice_number'] ?? 'n/a',
                number_format($candidate['amount'] ?? 0, 0),
                collect($candidate['allocations'] ?? [])->map(fn (array $allocation) => '#'.$allocation['invoice_line_id'].'='.number_format($allocation['amount'], 0))->implode(', '),
            ])->all(),
        );
    }
}
