<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Thêm loại phí thi lại (exam_resit_fee) vào ENUM finance_charges.charge_type.
 *
 * Phân biệt:
 *   - retake_fee     = phí học lại môn (học lại toàn bộ môn, có CourseRetakeRegistration flow)
 *   - exam_resit_fee = phí thi lại (chỉ thi lại bài thi, không có enrollment flow)
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
                'admission_fee'
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
