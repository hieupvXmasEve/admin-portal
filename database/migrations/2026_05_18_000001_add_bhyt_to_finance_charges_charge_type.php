<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add non-academic charge type `bhyt` to finance_charges.charge_type ENUM.
 *
 * Only `bhyt` is added in this phase per product decision Q1.
 * Additional non-academic types (health_check_fee, uniform_fee, etc.) are deferred.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE finance_charges
            MODIFY COLUMN charge_type ENUM(
                'tuition_term',
                'egc_level_fee',
                'retake_fee',
                'exam_resit_fee',
                'course_fee',
                'manual_fee',
                'defer_credit',
                'egc_exempt_credit',
                'scholarship_credit',
                'voucher_credit',
                'adjustment',
                'admission_fee',
                'bhyt'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE finance_charges
            MODIFY COLUMN charge_type ENUM(
                'tuition_term',
                'egc_level_fee',
                'retake_fee',
                'exam_resit_fee',
                'course_fee',
                'manual_fee',
                'defer_credit',
                'egc_exempt_credit',
                'scholarship_credit',
                'voucher_credit',
                'adjustment',
                'admission_fee'
            ) NOT NULL
        ");
    }
};
