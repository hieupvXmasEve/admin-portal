<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;

/**
 * Quản lý danh sách mã loại phí (fee_type) của hệ thống DNG.
 *
 * charge_type → DNG fee_type mapping is owned by ObligationTypeRegistry
 * (ADR-0027). This class keeps the staff dropdown of DNG codes and delegates
 * auto-mapping to the registry so per-type facts cannot drift.
 *
 * =====================================================================
 * LUỒNG SỬ DỤNG
 * =====================================================================
 *
 * 1. TẠO CHARGE (finance_charges):
 *    - Charge được tạo với charge_type nội bộ (vd: tuition_term, egc_level_fee).
 *    - Các type + DNG collection code được định nghĩa trong ObligationTypeRegistry.
 *    - Charge KHÔNG chứa mã DNG fee_type — việc chuyển đổi xảy ra ở bước push DNG.
 *
 * 2. TẠO DNG PAYMENT REQUEST (dng_payment_requests):
 *    - Khi push nợ lên DNG, mỗi request cần một fee_type theo chuẩn DNG.
 *    - Mã fee_type DNG được chọn theo hai cách:
 *      a) Tự động: dùng fromChargeType() → ObligationTypeRegistry::dngCollectionCodeFor().
 *      b) Thủ công: admin chọn từ dropdown all() tại trang tạo DNG thủ công.
 *    - Mã fee_type được lưu vào cột dng_payment_requests.fee_type.
 *
 * =====================================================================
 * KHI DNG CẤP MÃ FEE_TYPE MỚI / KHI THÊM OBLIGATION TYPE
 * =====================================================================
 *
 * Bước 1 — Thêm mã DNG vào all() nếu staff cần chọn thủ công.
 * Bước 2 — Thêm/ cập nhật entry trong ObligationTypeRegistry (dngCollectionCode).
 * Bước 3 — Nếu cần charge_type nội bộ mới: FinanceCharge::TYPE_*, migration ENUM,
 *           registry entry, parity tests.
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
            ['value' => 'HP',   'label' => 'HP: Học phí (tuition, EGC)'],
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
     * Source of truth: ObligationTypeRegistry::dngCollectionCodeFor().
     * Credits must not be pushed — callers filter amount_snapshot > 0 first.
     * Unmapped / null collection codes fall back to KHAC (legacy behaviour).
     */
    public static function fromChargeType(string $chargeType): string
    {
        return ObligationTypeRegistry::dngCollectionCodeFor($chargeType);
    }
}
