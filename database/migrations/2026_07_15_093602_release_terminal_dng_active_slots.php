<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('dng_payment_requests')
            ->whereNotNull('active_slot_key')
            ->whereIn('status', [
                'paid_uninvoiced',
                'paid_invoiced',
                'reconciled',
                'failed',
                'cancelled',
                'cancel_pushed_to_dng',
            ])
            ->update(['active_slot_key' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Released slot ownership cannot be reconstructed safely.
    }
};
