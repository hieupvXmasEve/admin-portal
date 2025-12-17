<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('voucher_redemptions')) {
            return;
        }

        // Add foreign key for billing_cycle_id if billing_cycles table exists
        if (Schema::hasTable('billing_cycles') && Schema::hasColumn('voucher_redemptions', 'billing_cycle_id')) {
            try {
                DB::statement('
                    ALTER TABLE voucher_redemptions
                    ADD CONSTRAINT voucher_redemptions_billing_cycle_id_foreign
                    FOREIGN KEY (billing_cycle_id) REFERENCES billing_cycles(id)
                    ON DELETE SET NULL
                ');
            } catch (\Exception $e) {
                // Foreign key might already exist, check error message
                if (
                    strpos($e->getMessage(), 'Duplicate key name') === false &&
                    strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate foreign key') === false &&
                    strpos($e->getMessage(), 'errno: 121') === false &&
                    strpos($e->getMessage(), 'Duplicate key on write or update') === false
                ) {
                    throw $e;
                }
            }
        }

        // Add foreign key for invoice_id if student_invoices table exists
        if (Schema::hasTable('student_invoices') && Schema::hasColumn('voucher_redemptions', 'invoice_id')) {
            try {
                DB::statement('
                    ALTER TABLE voucher_redemptions
                    ADD CONSTRAINT voucher_redemptions_invoice_id_foreign
                    FOREIGN KEY (invoice_id) REFERENCES student_invoices(id)
                    ON DELETE SET NULL
                ');
            } catch (\Exception $e) {
                // Foreign key might already exist, check error message
                if (
                    strpos($e->getMessage(), 'Duplicate key name') === false &&
                    strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate foreign key') === false &&
                    strpos($e->getMessage(), 'errno: 121') === false &&
                    strpos($e->getMessage(), 'Duplicate key on write or update') === false
                ) {
                    throw $e;
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('voucher_redemptions')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE voucher_redemptions DROP FOREIGN KEY voucher_redemptions_billing_cycle_id_foreign');
        } catch (\Exception $e) {
            // Foreign key might not exist, ignore
        }

        try {
            DB::statement('ALTER TABLE voucher_redemptions DROP FOREIGN KEY voucher_redemptions_invoice_id_foreign');
        } catch (\Exception $e) {
            // Foreign key might not exist, ignore
        }
    }
};
