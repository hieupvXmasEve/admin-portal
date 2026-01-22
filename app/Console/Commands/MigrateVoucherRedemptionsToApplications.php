<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VoucherRedemption;
use App\Models\VoucherApplication;
use App\Models\BillingCycle;
use App\Models\StudentInvoice;
use Illuminate\Support\Facades\DB;

class MigrateVoucherRedemptionsToApplications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from voucher_redemptions to voucher_applications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration of voucher redemptions to applications...');

        $redemptions = DB::table('voucher_redemptions')->get();

        if ($redemptions->isEmpty()) {
            $this->warn('No voucher redemptions found.');
            return;
        }

        $bar = $this->output->createProgressBar(count($redemptions));
        $bar->start();

        foreach ($redemptions as $redemption) {
            $semesterId = null;

            // Try find semester_id from billing_cycle
            if ($redemption->billing_cycle_id) {
                $billingCycle = DB::table('billing_cycles')->where('id', $redemption->billing_cycle_id)->first();
                if ($billingCycle) {
                    $semesterId = $billingCycle->semester_id;
                }
            }

            // Fallback to invoice if billing_cycle didn't work
            if (!$semesterId && $redemption->invoice_id) {
                $invoice = DB::table('student_invoices')->where('id', $redemption->invoice_id)->first();
                if ($invoice) {
                    $semesterId = $invoice->semester_id;
                }
            }

            if (!$semesterId) {
                $this->error("\nCould not find semester_id for redemption ID: " . $redemption->id . ". Skipping.");
                $bar->advance();
                continue;
            }

            // Map status
            $statusMapping = [
                'redeemed' => 'applied',
                'pending' => 'applied',
                'expired' => 'expired',
                'cancelled' => 'cancelled',
            ];

            $newStatus = $statusMapping[$redemption->status] ?? 'applied';

            try {
                DB::table('voucher_applications')->updateOrInsert(
                    [
                        'student_id' => $redemption->student_id,
                        'semester_id' => $semesterId,
                        'voucher_definition_id' => $redemption->voucher_id,
                    ],
                    [
                        'invoice_id' => $redemption->invoice_id,
                        'status' => $newStatus,
                        'applied_at' => $redemption->redeemed_at ?? $redemption->created_at,
                        'created_at' => $redemption->created_at,
                        'updated_at' => $redemption->updated_at,
                    ]
                );
            } catch (\Exception $e) {
                $this->error("\nError migrating redemption ID " . $redemption->id . ": " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nMigration completed.");
    }
}
