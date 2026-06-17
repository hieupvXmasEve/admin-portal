import type { BatchDiffBucket } from '@/types/finance';

const REASON_LABELS: Record<string, string> = {
    already_charged: 'Kỳ này đã có charge active cùng loại - không tạo lại',
    tuition_not_due_this_semester: 'Chưa tới kỳ phát sinh HP (trước intake_major)',
    zero_amount_term: 'Kỳ này không phát sinh HP (amount = 0)',
    missing_curriculum_version: 'Thiếu curriculum_version',
    missing_intake_semester: 'Thiếu intake_semester',
    missing_intake_major: 'Thiếu intake_major',
    missing_tuition_plan_term: 'Chưa cấu hình TuitionPlanTerm cho kỳ này',
    cannot_resolve_term_number: 'Không xác định được term number',
    already_fully_charged: 'Đã tạo đủ charge trong kỳ này',
    exceeded_max_level: 'Đã tới total level, không được tạo charge mới',
    studying_last_level: 'Đang học level cuối, không còn level tiếp theo để tạo phí dự kiến',
    no_chargeable_blocks_remaining: 'Không còn block hợp lệ để tạo',
    missing_current_level: 'Thiếu dữ liệu current level',
    missing_total_levels: 'Thiếu dữ liệu total levels',
    rerun_cancels_old_dng: 'Đã có DNG đang chờ — chạy lại sẽ hủy DNG cũ',
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
        label: 'Tạo mới',
        short: 'Tạo',
        dot: '🟢',
        badgeClass: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        cardClass: 'border-emerald-200/80 bg-emerald-50/40',
    },
    update: {
        label: 'Cập nhật-gộp',
        short: 'Cập nhật',
        dot: '🔵',
        badgeClass: 'border-blue-200 bg-blue-50 text-blue-800',
        cardClass: 'border-blue-200/80 bg-blue-50/40',
    },
    skip: {
        label: 'Bỏ qua',
        short: 'Bỏ qua',
        dot: '⚪',
        badgeClass: 'border-border bg-muted/60 text-muted-foreground',
        cardClass: 'border-border bg-muted/30',
    },
    warning: {
        label: 'Cảnh báo',
        short: 'Cảnh báo',
        dot: '🟠',
        badgeClass: 'border-amber-200 bg-amber-50 text-amber-800',
        cardClass: 'border-amber-200/80 bg-amber-50/40',
    },
};
