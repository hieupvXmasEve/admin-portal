<?php

namespace App\Console\Commands;

use App\Models\StudentInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixIncorrectScholarshipDiscounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:fix-scholarship-discounts {--dry-run : Run without making changes} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove scholarship discounts from invoices without tuition items and recalculate totals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding invoices with incorrect scholarship discounts...');
        $this->newLine();

        // Find invoices with scholarship discount but no tuition items
        $invoices = StudentInvoice::whereHas('discounts', function ($q) {
            $q->where('discount_type', 'scholarship');
        })
            ->whereDoesntHave('items', function ($q) {
                $q->where('item_type', 'tuition');
            })
            ->with(['student', 'items', 'discounts' => function ($q) {
                $q->where('discount_type', 'scholarship');
            }])
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('✓ No invoices found with incorrect scholarship discounts.');

            return self::SUCCESS;
        }

        $this->warn("Found {$invoices->count()} invoice(s) with incorrect scholarship discounts:");
        $this->newLine();

        // Display affected invoices
        $tableData = [];
        foreach ($invoices as $invoice) {
            $scholarshipDiscounts = $invoice->discounts->where('discount_type', 'scholarship');
            $scholarshipAmount = $scholarshipDiscounts->sum('amount');

            $tableData[] = [
                $invoice->id,
                $invoice->invoice_number,
                $invoice->student->student_id,
                $invoice->student->full_name,
                $invoice->student->status,
                number_format($invoice->subtotal, 0),
                number_format($scholarshipAmount, 0),
                $scholarshipDiscounts->pluck('discount_source')->implode(', '),
            ];
        }

        $this->table(
            ['ID', 'Invoice #', 'Student ID', 'Student Name', 'Status', 'Subtotal', 'Scholarship', 'Code'],
            $tableData
        );

        $this->newLine();

        // Dry run mode
        if ($this->option('dry-run')) {
            $this->info('DRY RUN MODE - No changes will be made.');
            $this->newLine();
            $this->info('Changes that would be made:');
            foreach ($invoices as $invoice) {
                $scholarshipDiscounts = $invoice->discounts->where('discount_type', 'scholarship');
                $this->line("  - Invoice #{$invoice->invoice_number}: Remove {$scholarshipDiscounts->count()} scholarship discount(s)");
            }

            return self::SUCCESS;
        }

        // Confirmation
        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to remove these scholarship discounts and recalculate invoice totals?', false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('Processing invoices...');

        $fixedCount = 0;
        $errorCount = 0;

        foreach ($invoices as $invoice) {
            try {
                DB::transaction(function () use ($invoice) {
                    // Get scholarship discounts before deletion
                    $scholarshipDiscounts = $invoice->discounts()->where('discount_type', 'scholarship')->get();

                    // Delete scholarship discounts
                    $invoice->discounts()->where('discount_type', 'scholarship')->delete();

                    // Recalculate invoice totals
                    $invoice->recalculateTotals();

                    $this->line("  ✓ Fixed Invoice #{$invoice->invoice_number} (Removed {$scholarshipDiscounts->count()} scholarship discount(s))");
                });

                $fixedCount++;
            } catch (\Exception $e) {
                $this->error("  ✗ Error fixing Invoice #{$invoice->invoice_number}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  - Fixed: {$fixedCount} invoice(s)");
        if ($errorCount > 0) {
            $this->line("  - Errors: {$errorCount} invoice(s)");
        }

        return self::SUCCESS;
    }
}
