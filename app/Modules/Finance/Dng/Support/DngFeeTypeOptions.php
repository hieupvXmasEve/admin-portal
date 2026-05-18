<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

use App\Models\FinanceCharge;

/**
 * Quản lý danh sách mã loại phí (fee_type) của hệ thống DNG.
 *
 * =====================================================================
 * LUỒNG SỬ DỤNG
 * =====================================================================
 *
 * 1. TẠO CHARGE (finance_charges):
 *    - Charge được tạo với charge_type nội bộ (vd: tuition_term, egc_level_fee).
 *    - Các charge_type được định nghĩa trong FinanceCharge::TYPE_*.
 *    - Charge KHÔNG chứa mã DNG fee_type — việc chuyển đổi xảy ra ở bước push DNG.
 *
 * 2. TẠO DNG PAYMENT REQUEST (dng_payment_requests):
 *    - Khi push nợ lên DNG, mỗi request cần một fee_type theo chuẩn DNG.
 *    - Mã fee_type DNG được chọn theo hai cách:
 *      a) Tự động: dùng fromChargeType() để map từ charge_type nội bộ sang mã DNG.
 *         Dùng trong BatchDng và các flow tự động gộp charge → DNG.
 *      b) Thủ công: admin chọn từ dropdown all() tại trang tạo DNG thủ công
 *         (/finance/payments/create, /finance/operations/batch-dng).
 *    - Mã fee_type được lưu vào cột dng_payment_requests.fee_type.
 *
 * =====================================================================
 * KHI DNG CẤP MÃ FEE_TYPE MỚI
 * =====================================================================
 *
 * Bước 1 — Thêm vào all():
 *   ['value' => 'MA_MOI', 'label' => 'MA_MOI: Mô tả ngắn gọn'],
 *
 * Bước 2 — Thêm vào fromChargeType() nếu có charge_type nội bộ tương ứng:
 *   FinanceCharge::TYPE_XXX => 'MA_MOI',
 *   Nếu không có charge_type tương ứng (phí chỉ tạo thủ công), bỏ qua bước này.
 *
 * Bước 3 — Nếu cần charge_type nội bộ mới (phí hoàn toàn mới chưa có trong hệ thống):
 *   - Thêm hằng TYPE_XXX vào FinanceCharge.
 *   - Thêm vào cột ENUM finance_charges.charge_type qua migration.
 *   - Map trong fromChargeType() như bước 2.
 * =====================================================================
 */
final class DngFeeTypeOptions
{
    /**
     * Danh sách mã fee_type DNG được phép sử dụng.
     * Dùng để hiển thị dropdown khi tạo DNG payment request thủ công.
     * Mã GC vẫn giữ để tương thích với dữ liệu cũ, nhưng không dùng cho charge mới.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function all(): array
    {
        return [
            ['value' => 'HP',   'label' => 'HP: Học phí (tuition, EGC, course)'],
            ['value' => 'BHYT', 'label' => 'BHYT: Bảo hiểm y tế'],
            ['value' => 'HL',   'label' => 'HL: Học lại'],
            ['value' => 'PRE',  'label' => 'PRE: Lệ phí xét tuyển'],
            ['value' => 'KHAC', 'label' => 'KHAC: Các loại phí đào tạo khác'],
            ['value' => 'THHB', 'label' => 'THHB: Thu hồi học bổng'],
            ['value' => 'GC',   'label' => 'GC: Học phí GC (legacy — không dùng cho charge mới)'],
            ['value' => 'F1',   'label' => 'F1: Phí giữ chỗ học bổng'],
            ['value' => 'PTL',  'label' => 'PTL: Phí Thi lại'],
        ];
    }

    /**
     * Trả về mảng các giá trị fee_type hợp lệ.
     * Dùng để validate dng_payment_requests.fee_type trong FormRequest.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::all(), 'value');
    }

    /**
     * Chuyển đổi charge_type nội bộ (finance_charges.charge_type) sang mã fee_type DNG.
     *
     * Quy tắc hiện tại:
     *   - tuition_term, egc_level_fee, course_fee → HP   (học phí chính)
     *   - retake_fee                              → HL   (học lại môn)
     *   - exam_resit_fee                          → PTL  (phí thi lại)
     *   - manual_fee, adjustment                  → KHAC (phí khác)
     *   - các loại credit (âm) KHÔNG được map tại đây;
     *     caller phải lọc amount_snapshot > 0 trước khi gọi hàm này.
     */
    public static function fromChargeType(string $chargeType): string
    {
        return match ($chargeType) {
            FinanceCharge::TYPE_TUITION_TERM,
            FinanceCharge::TYPE_COURSE_FEE,
            FinanceCharge::TYPE_EGC_LEVEL_FEE => 'HP',
            FinanceCharge::TYPE_RETAKE_FEE => 'HL',
            FinanceCharge::TYPE_EXAM_RESIT_FEE => 'PTL',
            FinanceCharge::TYPE_BHYT => 'BHYT',
            FinanceCharge::TYPE_MANUAL_FEE,
            FinanceCharge::TYPE_ADJUSTMENT => 'KHAC',
            default => 'KHAC',
        };
    }
}
