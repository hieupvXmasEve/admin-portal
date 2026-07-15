<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private const CHARGE_TYPES = "'tuition_term','egc_level_fee','retake_fee','exam_resit_fee','manual_fee','admission_fee','defer_credit','egc_exempt_credit','scholarship_credit','voucher_credit','adjustment','bhyt'";

    private const DEBIT_TYPES = "'tuition_term','egc_level_fee','retake_fee','exam_resit_fee','manual_fee','admission_fee','bhyt'";

    private const CREDIT_TYPES = "'defer_credit','egc_exempt_credit','scholarship_credit','voucher_credit'";

    public function up(): void
    {
        if (DB::table('finance_charges')->where('charge_type', 'course_fee')->exists()) {
            throw new RuntimeException('Cannot retire course_fee while finance_charges still contains course_fee rows.');
        }

        DB::statement(
            'ALTER TABLE `finance_charges` '
            .'DROP CONSTRAINT IF EXISTS `chk_finance_charges_amount_sign`, '
            .'MODIFY COLUMN `charge_type` ENUM('.self::CHARGE_TYPES.') NOT NULL, '
            .'ADD CONSTRAINT `chk_finance_charges_amount_sign` CHECK ('
            .'((charge_type IN ('.self::DEBIT_TYPES.') AND amount >= 0)'
            .' OR (charge_type IN ('.self::CREDIT_TYPES.') AND amount <= 0)'
            ." OR charge_type = 'adjustment'))"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE `finance_charges` '
            .'DROP CONSTRAINT IF EXISTS `chk_finance_charges_amount_sign`, '
            ."MODIFY COLUMN `charge_type` ENUM('tuition_term','egc_level_fee','retake_fee','exam_resit_fee','course_fee','manual_fee','admission_fee','defer_credit','egc_exempt_credit','scholarship_credit','voucher_credit','adjustment','bhyt') NOT NULL, "
            .'ADD CONSTRAINT `chk_finance_charges_amount_sign` CHECK ('
            ."((charge_type IN ('tuition_term','egc_level_fee','retake_fee','exam_resit_fee','course_fee','manual_fee','admission_fee','bhyt') AND amount >= 0)"
            ." OR (charge_type IN ('defer_credit','egc_exempt_credit','scholarship_credit','voucher_credit') AND amount <= 0)"
            ." OR charge_type = 'adjustment'))"
        );
    }
};
