import type { BatchDiffBucket } from '@/types/finance';

const REASON_LABELS: Record<string, string> = {
    already_charged: 'Kỳ này đã có khoản phí đang hiệu lực cùng loại — không tạo lại',
    scholarship_review_pending: 'Đang chờ xét duyệt điều chỉnh học bổng - chưa tạo học phí',
    scholarship_restoration_pending: 'Đang chờ duyệt đề xuất khôi phục học bổng - chưa tạo học phí',
    tuition_not_due_this_semester: 'Chưa tới kỳ phát sinh HP (trước kỳ nhập ngành)',
    zero_amount_term: 'Kỳ này không phát sinh HP (số tiền = 0)',
    missing_curriculum_version: 'Thiếu phiên bản chương trình đào tạo',
    missing_intake_semester: 'Thiếu kỳ nhập học',
    missing_intake_major: 'Thiếu kỳ nhập ngành',
    missing_tuition_plan_term: 'Chưa cấu hình đợt học phí cho kỳ này',
    cannot_resolve_term_number: 'Không xác định được số đợt',
    already_fully_charged: 'Đã tạo đủ khoản phí trong kỳ này',
    exceeded_max_level: 'Đã tới level tối đa, không tạo khoản phí mới',
    studying_last_level: 'Đang học level cuối, không còn level tiếp theo để tạo phí dự kiến',
    no_chargeable_blocks_remaining: 'Không còn block hợp lệ để tạo',
    missing_current_level: 'Thiếu dữ liệu level hiện tại',
    missing_total_levels: 'Thiếu dữ liệu tổng số level',
    already_generated: 'Đã có block EGC đang hiệu lực trong kỳ này',
    egc_block_missing_charge: 'Block EGC đã có nhưng thiếu khoản phí - cần sinh lại trên block hiện có',
    egc_block_charge_voided: 'Block EGC cũ đã bị hủy khoản phí - sinh lại sẽ gắn vào block hiện có',
    egc_block_charge_non_collectible: 'Block EGC đã có nhưng khoản phí không còn thu được - cần rà soát/sinh lại',
    egc_block_reissue_existing_blocks: 'Sinh lại khoản phí trên block EGC hiện có',
    live_dng_review_required: 'Đang có DNG live - cần xử lý DNG trước',
    paid_dng_or_payment_review_required: 'Đã có bằng chứng thanh toán/DNG - cần rà soát trước',
    deferred_non_billable: 'Kỳ bảo lưu không phát sinh phí',
    inconsistent_block_shape: 'Dữ liệu block EGC không nhất quán - cần sửa thủ công',
    active_dng_replacement_required: 'Còn phần chưa nằm trong lệnh thu — sẽ hủy lệnh cũ và đẩy lệnh mới gồm toàn bộ',
    active_dng_already_covers_payable: 'Đã có lệnh thu đủ',
    active_dng_coverage_unknown: 'Không xác định được phần đã thu — cần kiểm tra thủ công',
    active_dng_replacement_disabled: 'Tự động thay thế lệnh thu đang tắt — cần xử lý thủ công',
    no_email: 'Không có email',
    recently_reminded: 'Đã nhắc trong 24h gần đây',
    scholarship_expired: 'Học bổng đã hết hạn',
};

export function batchReasonLabel(reason: string | null | undefined): string {
    if (!reason) return '—';
    return REASON_LABELS[reason] ?? reason.replace(/_/g, ' ');
}

export const BATCH_BUCKET_META: Record<BatchDiffBucket, { label: string; short: string; dot: string; badgeClass: string; cardClass: string }> = {
    create: {
        label: 'Cần tạo',
        short: 'Tạo',
        dot: '🟢',
        badgeClass: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        cardClass: 'border-emerald-200/80 bg-emerald-50/40',
    },
    update: {
        label: 'Cần thay',
        short: 'Thay',
        dot: '🔵',
        badgeClass: 'border-blue-200 bg-blue-50 text-blue-800',
        cardClass: 'border-blue-200/80 bg-blue-50/40',
    },
    skip: {
        label: 'Không cần',
        short: 'Không',
        dot: '⚪',
        badgeClass: 'border-border bg-muted/60 text-muted-foreground',
        cardClass: 'border-border bg-muted/30',
    },
    warning: {
        label: 'Cần kiểm tra',
        short: 'Kiểm tra',
        dot: '🟠',
        badgeClass: 'border-amber-200 bg-amber-50 text-amber-800',
        cardClass: 'border-amber-200/80 bg-amber-50/40',
    },
};
