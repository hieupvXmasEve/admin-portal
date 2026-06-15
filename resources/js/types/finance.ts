/**
 * Finance Module Types
 */

export interface FinanceCharge {
    id: number;
    student_id: number;
    student?: StudentBasic;
    semester_id?: number;
    semester?: Semester;
    billing_cycle_id?: number;
    charge_type: ChargeType;
    description: string;
    amount: number;
    status: ChargeStatus;
    due_date?: string;
    source_type?: string;
    source_id?: number;
    voided_at?: string;
    voided_by_user_id?: number;
    voided_by?: User;
    void_reason?: string;
    created_by_user_id?: number;
    created_by?: User;
    created_at: string;
    updated_at: string;

    // Computed
    is_charge?: boolean;
    is_credit?: boolean;
    paid_amount?: number;
    balance?: number;
    is_fully_paid?: boolean;
}

export interface Payment {
    id: number;
    student_id: number;
    student?: StudentBasic;
    amount: number;
    method: PaymentMethod;
    source?: string;
    external_ref?: string;
    paid_at: string;
    status: PaymentStatus;
    received_by_user_id?: number;
    received_by?: User;
    notes?: string;
    created_at: string;
    updated_at: string;

    // Computed
    allocated_amount?: number;
    unapplied_amount?: number;
    is_fully_allocated?: boolean;
}

export interface PaymentAllocation {
    id: number;
    payment_id: number;
    payment?: Payment;
    finance_charge_id: number;
    charge?: FinanceCharge;
    allocated_amount: number;
    allocated_at: string;
}

export interface StudentBasic {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    status?: string;
}

export interface Semester {
    id: number;
    name: string;
    code: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
}

// Enums
export type ChargeType = 'tuition_term' | 'egc_level_fee' | 'retake_fee' | 'exam_resit_fee' | 'manual_fee' | 'admission_fee' | 'defer_credit' | 'egc_exempt_credit' | 'scholarship_credit' | 'voucher_credit' | 'adjustment';

export type ChargeStatus = 'active' | 'void' | 'transferred';

export type PaymentMethod = 'cash' | 'bank_transfer' | 'gateway' | 'wallet' | 'import' | 'other';

export type PaymentStatus = 'pending' | 'completed' | 'refunded' | 'cancelled';

// Labels
export const CHARGE_TYPE_LABELS: Record<ChargeType, string> = {
    tuition_term: 'Học phí kỳ (Tuition)',
    egc_level_fee: 'Phí EGC (EGC Level Fee)',
    retake_fee: 'Phí học lại môn (Course Retake)',
    exam_resit_fee: 'Phí thi lại (Exam Resit)',
    manual_fee: 'Phí thủ công (Manual Fee)',
    admission_fee: 'Lệ phí xét tuyển (Admission Fee)',
    defer_credit: 'Hoàn phí bảo lưu (Defer Credit)',
    egc_exempt_credit: 'Miễn EGC (EGC Exempt)',
    scholarship_credit: 'Học bổng (Scholarship)',
    voucher_credit: 'Voucher',
    adjustment: 'Điều chỉnh (Adjustment)',
};

export const CHARGE_STATUS_LABELS: Record<ChargeStatus, string> = {
    active: 'Hoạt động (Active)',
    void: 'Đã hủy (Voided)',
    transferred: 'Đã chuyển (Transferred)',
};

export const PAYMENT_METHOD_LABELS: Record<PaymentMethod, string> = {
    cash: 'Tiền mặt (Cash)',
    bank_transfer: 'Chuyển khoản (Bank Transfer)',
    gateway: 'Cổng thanh toán (Gateway)',
    wallet: 'Ví điện tử (Wallet)',
    import: 'Nhập từ file (Import)',
    other: 'Khác (Other)',
};

export const PAYMENT_STATUS_LABELS: Record<PaymentStatus, string> = {
    pending: 'Chờ xử lý (Pending)',
    completed: 'Hoàn thành (Completed)',
    refunded: 'Hoàn tiền (Refunded)',
    cancelled: 'Đã hủy (Cancelled)',
};

// Badge classes
export const CHARGE_TYPE_BADGE_CLASSES: Record<ChargeType, string> = {
    tuition_term: 'bg-blue-100 text-blue-800',
    egc_level_fee: 'bg-indigo-100 text-indigo-800',
    retake_fee: 'bg-orange-100 text-orange-800',
    exam_resit_fee: 'bg-red-100 text-red-800',
    manual_fee: 'bg-gray-100 text-gray-800',
    admission_fee: 'bg-pink-100 text-pink-800',
    defer_credit: 'bg-green-100 text-green-800',
    egc_exempt_credit: 'bg-emerald-100 text-emerald-800',
    scholarship_credit: 'bg-teal-100 text-teal-800',
    voucher_credit: 'bg-lime-100 text-lime-800',
    adjustment: 'bg-yellow-100 text-yellow-800',
};

export const CHARGE_STATUS_BADGE_CLASSES: Record<ChargeStatus, string> = {
    active: 'bg-green-100 text-green-800',
    void: 'bg-red-100 text-red-800',
    transferred: 'bg-purple-100 text-purple-800',
};

export const PAYMENT_STATUS_BADGE_CLASSES: Record<PaymentStatus, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800',
    refunded: 'bg-purple-100 text-purple-800',
    cancelled: 'bg-red-100 text-red-800',
};

// Helper functions
export function getChargeTypeLabel(type: ChargeType): string {
    return CHARGE_TYPE_LABELS[type] ?? type;
}

export function getChargeTypeBadgeClass(type: ChargeType): string {
    return CHARGE_TYPE_BADGE_CLASSES[type] ?? 'bg-gray-100 text-gray-800';
}

export function getChargeStatusLabel(status: ChargeStatus): string {
    return CHARGE_STATUS_LABELS[status] ?? status;
}

export function getChargeStatusBadgeClass(status: ChargeStatus): string {
    return CHARGE_STATUS_BADGE_CLASSES[status] ?? 'bg-gray-100 text-gray-800';
}

export function getPaymentMethodLabel(method: PaymentMethod): string {
    return PAYMENT_METHOD_LABELS[method] ?? method;
}

export function getPaymentStatusLabel(status: PaymentStatus): string {
    return PAYMENT_STATUS_LABELS[status] ?? status;
}

export function getPaymentStatusBadgeClass(status: PaymentStatus): string {
    return PAYMENT_STATUS_BADGE_CLASSES[status] ?? 'bg-gray-100 text-gray-800';
}

export function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
}

export function isCredit(chargeType: ChargeType): boolean {
    return ['defer_credit', 'egc_exempt_credit', 'scholarship_credit', 'voucher_credit'].includes(chargeType);
}

// =====================================================================
// Finance Office shell + Student 360 (S-010 milestone 1)
// =====================================================================

export interface SemesterOption {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
}

export interface SemesterContext {
    selected_id: number | null;
    options: SemesterOption[];
}

export interface Student360Identity {
    id: number;
    student_code: string;
    full_name: string;
    status: string;
    academic_status: string | null;
    lifecycle_reason: string;
    lifecycle_label: string;
}

export interface Student360Balances {
    net_charges: number;
    total_paid: number;
    balance: number;
    unapplied_credit: number;
    status: string;
}

export interface LedgerEvent {
    at: string | null;
    type: string;
    signed_amount: number;
    label: string;
    refs: Record<string, number>;
}

export interface FinanceSearchResult {
    type: string;
    id: number;
    label: string;
    sublabel: string;
    url: string;
}

// =====================================================================
// Student 360 Milestone 2 display types
// =====================================================================

export interface DngCardRequest {
    id: number;
    status: string;
    item_id: string;
    amount: number;
    error_message: string | null;
}

export interface Student360StatusCards {
    balance: {
        balance: number;
        unapplied_credit: number;
        has_unapplied: boolean;
        unapplied_payment_id: number | null;
    };
    dng: { has_active: boolean; request: DngCardRequest | null };
    installments: {
        total: number;
        paid: number;
        next: {
            id: number;
            charge_id: number;
            installment_no: number;
            due_date: string | null;
            has_push_error: boolean;
        } | null;
    };
    exception: {
        needs_review: boolean;
        dng_request_id: number | null;
        blocking_reasons: string[];
    };
}

export interface Student360Actions {
    can_record_payment: boolean;
    can_allocate: boolean;
    can_cancel_dng: boolean;
    can_void_charges: boolean;
}

export interface LedgerInvoiceLine {
    id: number;
    label: string;
    outstanding: number;
}

export interface LedgerInvoice {
    id: number;
    invoice_number: string;
    status: string;
    net: number;
    paid: number;
    remaining: number;
    lines: LedgerInvoiceLine[];
}

export interface LedgerGroup {
    semester: { id: number | null; name: string };
    invoices: LedgerInvoice[];
}

export type BatchDiffBucket = 'create' | 'update' | 'skip' | 'warning';

export interface BatchPreviewLineDisplay {
    student_id: string;
    label: string;
    diff: BatchDiffBucket;
    gross?: number;
    discount?: number;
    net: number;
    installment_aware_total?: number;
    reason?: string | null;
    warning_codes?: string[];
}

export interface BatchPreviewLineClient {
    key: string;
    display: BatchPreviewLineDisplay;
}

export interface BatchPreviewResponse {
    preview_token: string;
    lines: BatchPreviewLineClient[];
    summary: Record<string, number>;
}

export type BatchJobKind = 'charge_generation' | 'dng_push' | 'reminder';

export interface BatchResult {
    job: BatchJobKind;
    fee_category?: string;
    summary: Record<string, unknown> & { errors?: string[] };
}
