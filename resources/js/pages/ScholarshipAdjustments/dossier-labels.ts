/**
 * Nhãn tiếng Việt cho các giá trị enum của quy trình điều chỉnh học bổng.
 *
 * Backend lưu giá trị máy (`ready_for_decision`, `suspend_full`, …); không màn
 * hình nào của tính năng này được hiển thị thẳng giá trị đó. Dùng chung cho
 * Index.vue, Show.vue và CandidatesPreview.vue để chữ nghĩa thống nhất giữa
 * danh sách, trang chi tiết và màn hình quét sinh viên.
 *
 * Nguồn giá trị chuẩn:
 * app/Modules/Academic/Progression/Models/ScholarshipAdjustmentDossier.php
 */

type LabelMap = Record<string, string>;

export const DOSSIER_STATUS_LABELS: LabelMap = {
    identified: 'Mới ghi nhận',
    interview_scheduled: 'Đã hẹn phỏng vấn',
    interviewed: 'Đã phỏng vấn',
    awaiting_student_confirmation: 'Chờ sinh viên xác nhận',
    ready_for_decision: 'Chờ duyệt',
    approved: 'Đã duyệt',
    applied: 'Đã áp dụng vào học phí',
    closed: 'Đã đóng',
    student_disputed: 'Sinh viên không đồng ý',
    student_no_show: 'Sinh viên không dự phỏng vấn',
    confirmation_overdue: 'Quá hạn xác nhận',
    no_adjustment: 'Giữ nguyên học bổng',
    cancelled: 'Đã huỷ',
    finance_review_required: 'Cần phòng tài chính kiểm tra',
    not_applicable: 'Không áp dụng — kỳ không thu học phí',
};

export const INTERVIEW_STATUS_LABELS: LabelMap = {
    not_scheduled: 'Chưa hẹn lịch',
    scheduled: 'Đã hẹn lịch',
    completed: 'Đã hoàn tất',
    student_no_show: 'Sinh viên không dự',
    rescheduled: 'Đã dời lịch',
    cancelled: 'Đã huỷ',
};

export const CONFIRMATION_STATUS_LABELS: LabelMap = {
    pending: 'Chờ sinh viên phản hồi',
    confirmed: 'Sinh viên đã đồng ý',
    disputed: 'Sinh viên không đồng ý',
    declined: 'Sinh viên từ chối',
    overdue: 'Quá hạn phản hồi',
    dispute_overruled: 'Người duyệt đã bác bỏ phản đối',
};

export const DECISION_TYPE_LABELS: LabelMap = {
    keep: 'Giữ nguyên học bổng',
    reduce: 'Giảm học bổng',
    suspend_full: 'Cắt toàn bộ học bổng',
    defer: 'Tạm hoãn quyết định',
    cancel: 'Huỷ học bổng',
};

export const DOSSIER_SOURCE_LABELS: LabelMap = {
    system: 'Hệ thống tự phát hiện',
    manual: 'Thêm thủ công',
};

// Giá trị lưu trong DB là `fixed_amount` (xem enum của scholarship_definitions)
// — trước đây map khoá theo `fixed` nên mọi học bổng dạng số tiền cố định đều
// bị hiển thị nguyên giá trị thô của cột.
export const SCHOLARSHIP_TYPE_LABELS: LabelMap = {
    percentage: 'Theo phần trăm',
    fixed_amount: 'Số tiền cố định',
};

/**
 * Giá trị chưa có nhãn vẫn phải đọc được như chữ thường, không phải giá trị thô
 * của cột: `finance_review_required` được đổi thành "Finance review required".
 * Thiếu nhãn là lỗi cần bổ sung ở trên, nhưng người dùng không phải là người
 * nhìn thấy nó.
 */
export function labelFor(map: LabelMap, value: string | null | undefined, fallback = '—'): string {
    if (!value) return fallback;
    if (map[value]) return map[value];

    const humanised = value.replace(/[_-]+/g, ' ').trim();

    return humanised.charAt(0).toUpperCase() + humanised.slice(1);
}
