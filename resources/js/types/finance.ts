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
export type ChargeType = 'tuition_term' | 'egc_level_fee' | 'retake_fee' | 'exam_resit_fee' | 'course_fee' | 'manual_fee' | 'admission_fee' | 'defer_credit' | 'egc_exempt_credit' | 'scholarship_credit' | 'voucher_credit' | 'adjustment' | 'bhyt';

export type InvoiceStatus = 'draft' | 'pending' | 'partial' | 'paid' | 'overdue' | 'cancelled' | 'void';

export type ChargeStatus = 'active' | 'void' | 'transferred';

export type PaymentMethod = 'cash' | 'bank_transfer' | 'gateway' | 'wallet' | 'import' | 'other';

export type PaymentStatus = 'pending' | 'completed' | 'refunded' | 'cancelled';

// Labels
export const CHARGE_TYPE_LABELS: Record<ChargeType, string> = {
    tuition_term: 'Học phí kỳ (Tuition)',
    egc_level_fee: 'Phí EGC (EGC Level Fee)',
    retake_fee: 'Phí học lại môn (Course Retake)',
    exam_resit_fee: 'Phí thi lại (Exam Resit)',
    course_fee: 'Phí môn học (Course Fee, đã ngừng)',
    manual_fee: 'Phí thủ công (Manual Fee)',
    admission_fee: 'Lệ phí xét tuyển (Admission Fee)',
    defer_credit: 'Hoàn phí bảo lưu (Defer Credit)',
    egc_exempt_credit: 'Miễn EGC (EGC Exempt)',
    scholarship_credit: 'Học bổng (Scholarship)',
    voucher_credit: 'Voucher',
    adjustment: 'Điều chỉnh (Adjustment)',
    bhyt: 'BHYT',
};

export const INVOICE_STATUS_LABELS: Record<InvoiceStatus, string> = {
    draft: 'Nháp',
    pending: 'Chờ thanh toán',
    partial: 'Thanh toán một phần',
    paid: 'Đã thanh toán',
    overdue: 'Quá hạn',
    cancelled: 'Đã hủy',
    void: 'Đã hủy',
};

export const INVOICE_STATUS_BADGE_CLASSES: Record<InvoiceStatus, string> = {
    draft: 'bg-slate-100 text-slate-700 border-slate-200',
    pending: 'bg-blue-50 text-blue-700 border-blue-200',
    partial: 'bg-amber-50 text-amber-800 border-amber-200',
    paid: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    overdue: 'bg-red-50 text-red-700 border-red-200',
    cancelled: 'bg-gray-100 text-gray-500 border-gray-200',
    void: 'bg-gray-100 text-gray-500 border-gray-200',
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
    course_fee: 'bg-violet-100 text-violet-800',
    manual_fee: 'bg-gray-100 text-gray-800',
    admission_fee: 'bg-pink-100 text-pink-800',
    defer_credit: 'bg-green-100 text-green-800',
    egc_exempt_credit: 'bg-emerald-100 text-emerald-800',
    scholarship_credit: 'bg-teal-100 text-teal-800',
    voucher_credit: 'bg-lime-100 text-lime-800',
    adjustment: 'bg-yellow-100 text-yellow-800',
    bhyt: 'bg-cyan-100 text-cyan-800',
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
export function getChargeTypeLabel(type: string): string {
    return CHARGE_TYPE_LABELS[type as ChargeType] ?? type;
}

export function getChargeTypeBadgeClass(type: string): string {
    return CHARGE_TYPE_BADGE_CLASSES[type as ChargeType] ?? 'bg-gray-100 text-gray-800';
}

export function getInvoiceStatusLabel(status: string): string {
    return INVOICE_STATUS_LABELS[status as InvoiceStatus] ?? status;
}

export function getInvoiceStatusBadgeClass(status: string): string {
    return INVOICE_STATUS_BADGE_CLASSES[status as InvoiceStatus] ?? 'bg-gray-100 text-gray-800 border-gray-200';
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
    net_charges: number | null;
    total_paid: number | null;
    balance: number | null;
    unapplied_credit: number | null;
    credit_applied: number | null;
    status: string;
    valid: boolean;
    message: string | null;
    issues: Array<{ code: string; blocking: boolean; evidence: Record<string, number | string> }>;
}

export interface StudentFinanceOverviewKpi {
    label: string;
    amount: number | null;
    primary: boolean;
}

export interface StudentFinanceTuitionOverview {
    title: string;
    kpis: {
        collectible_due: StudentFinanceOverviewKpi;
        total_paid: StudentFinanceOverviewKpi;
        collected: StudentFinanceOverviewKpi;
        credit_applied: StudentFinanceOverviewKpi;
        surplus: StudentFinanceOverviewKpi;
    };
    settlement: {
        valid: boolean;
        state: string;
        message: string | null;
        issues: Array<{ code: string; blocking: boolean; evidence: Record<string, number | string> }>;
    };
    surplus_message: string | null;
}

export interface StudentFinancePaymentHistoryRow {
    id: number;
    paid_at: string | null;
    source: string;
    source_label: string;
    reference: string;
    dng_request_id: number | null;
    dng_request_reference: string | null;
    amount_paid: number;
    collected_amount: number;
    surplus_amount: number;
    status: 'settled' | 'surplus';
    status_label: string;
    action: {
        can_allocate: boolean;
        message: string | null;
    };
}

export type StudentFinanceReviewSignalTarget = 'payment-history' | 'ledger' | 'installments';

export interface StudentFinanceReviewSignal {
    id: string;
    type: 'surplus' | 'dng_paid_voided_fee' | 'installment_mismatch' | 'invoice_cache_drift';
    title: string;
    message: string;
    target: StudentFinanceReviewSignalTarget;
    target_label: string;
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
        valid: boolean;
        balance: number | null;
        unapplied_cash: number | null;
        unapplied_credit: number | null;
        applied_credit: number | null;
        has_unapplied: boolean;
        unapplied_payment_id: number | null;
        message: string | null;
        issues: Array<{ code: string; blocking: boolean; evidence: Record<string, number | string> }>;
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
    can_refund_surplus: boolean;
    can_forfeit_surplus: boolean;
    can_cancel_dng: boolean;
    can_void_charges: boolean;
}

export interface LedgerInvoiceLine {
    id: number;
    charge_type: string;
    label: string;
    description: string;
    amount: number;
    paid: number;
    outstanding: number;
    is_credit: boolean;
    status: 'active' | 'void';
    status_label: string;
    voided_at: string | null;
    void_reason: string | null;
    payment_applied: number;
    payment_reversed: number;
}

export interface LedgerInvoice {
    id: number;
    invoice_number: string;
    status: string;
    gross: number;
    discount: number;
    net: number;
    paid: number;
    remaining: number;
    due_date: string | null;
    paid_at: string | null;
    lines: LedgerInvoiceLine[];
}

export interface LedgerGroup {
    semester: { id: number | null; code: string | null; name: string };
    collectible_total: number;
    collectible_paid: number;
    collectible_remaining: number;
    state_label: string;
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
    block_count?: number | null;
    block_amounts?: number[];
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

export interface CockpitKpi {
    total_receivable: number;
    total_collected: number;
    collected_pct: number;
    uncharged_count: number;
}

export interface CockpitQueue {
    key: string;
    label: string;
    count: number;
    obeys_semester: boolean;
    scope_badge: 'campus' | 'semester' | 'multi';
    permission: string;
    action_url: string;
    severity: 'critical' | 'action' | 'normal';
}

export interface CockpitPhase {
    key: 'early' | 'mid' | 'late';
    label: string;
    source: string;
}

export interface CockpitInvariant {
    code: string;
    severity: string;
    label: string;
    count: number | null;
    error: string | null;
}

export interface CockpitDataHealth {
    scope_badge: 'campus' | 'all_campus' | 'semester';
    critical_count: number;
    invariants: CockpitInvariant[];
    balance_match: { match_pct: number; matched: number; denominator: number };
}

export interface CockpitQueueRow {
    id: number;
    title: string;
    subtitle: string;
    age: string | null;
    student_id: number | null;
    primary_action: { kind: string; url: string; label: string };
}
