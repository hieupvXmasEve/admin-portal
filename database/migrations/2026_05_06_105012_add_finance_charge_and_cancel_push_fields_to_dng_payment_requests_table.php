<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            // Links this DNG request directly to the FinanceCharge it covers.
            // Nullable: older records and non-charge DNG requests (e.g. tuition) may not have one.
            $table->foreignId('finance_charge_id')
                ->nullable()
                ->after('semester_id')
                ->constrained('finance_charges')
                ->nullOnDelete();

            // Audit trail for DNG cancel API calls.
            $table->json('cancel_push_payload')->nullable()->after('push_response');
            $table->json('cancel_push_response')->nullable()->after('cancel_push_payload');
        });
    }

    public function down(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            $table->dropColumn(['cancel_push_payload', 'cancel_push_response']);
            $table->dropForeign(['finance_charge_id']);
            $table->dropColumn('finance_charge_id');
        });
    }
};
