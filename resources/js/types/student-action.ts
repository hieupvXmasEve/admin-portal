/**
 * Student Administrative Actions Types
 */

export enum StudentActionType {
    ACADEMIC_DEFER = 'ACADEMIC_DEFER',
    ACADEMIC_RESUME = 'ACADEMIC_RESUME',
    WAITING_COURSE_OPENING = 'WAITING_COURSE_OPENING',
    ADMISSION_DEFERRAL = 'ADMISSION_DEFERRAL',
    ACADEMIC_DROPOUT = 'ACADEMIC_DROPOUT',
    CAMPUS_TRANSFER = 'CAMPUS_TRANSFER',
}

export const STUDENT_ACTION_TYPE_LABELS: Record<StudentActionType, string> = {
    [StudentActionType.ACADEMIC_DEFER]: 'Bảo lưu (Defer)',
    [StudentActionType.ACADEMIC_RESUME]: 'Quay lại học (Resume)',
    [StudentActionType.WAITING_COURSE_OPENING]: 'Chờ mở môn (Waiting for Course Opening)',
    [StudentActionType.ADMISSION_DEFERRAL]: 'Hoãn nhập học (Admission Deferral)',
    [StudentActionType.ACADEMIC_DROPOUT]: 'Bỏ học (Dropout)',
    [StudentActionType.CAMPUS_TRANSFER]: 'Chuyển campus (Campus Transfer)',
};

export const STUDENT_ACTION_TYPE_LABELS_EN: Record<StudentActionType, string> = {
    [StudentActionType.ACADEMIC_DEFER]: 'Academic Defer',
    [StudentActionType.ACADEMIC_RESUME]: 'Academic Resume',
    [StudentActionType.WAITING_COURSE_OPENING]: 'Waiting for Course Opening',
    [StudentActionType.ADMISSION_DEFERRAL]: 'Admission Deferral',
    [StudentActionType.ACADEMIC_DROPOUT]: 'Academic Dropout',
    [StudentActionType.CAMPUS_TRANSFER]: 'Campus Transfer',
};

export const STUDENT_ACTION_TYPE_DESCRIPTIONS: Record<StudentActionType, string> = {
    [StudentActionType.ACADEMIC_DEFER]: 'Student takes a leave of absence and will return in a future semester',
    [StudentActionType.ACADEMIC_RESUME]: 'Student returns from deferral to continue their studies',
    [StudentActionType.WAITING_COURSE_OPENING]: 'Student is temporarily paused awaiting a suitable course or class section to open',
    [StudentActionType.ADMISSION_DEFERRAL]: 'New student defers their admission or is a no-show',
    [StudentActionType.ACADEMIC_DROPOUT]: 'Student permanently leaves the program',
    [StudentActionType.CAMPUS_TRANSFER]: 'Student transfers to a different campus',
};

export const STUDENT_ACTION_TYPE_BADGE_CLASSES: Record<StudentActionType, string> = {
    [StudentActionType.ACADEMIC_DEFER]: 'bg-orange-100 text-orange-800',
    [StudentActionType.ACADEMIC_RESUME]: 'bg-green-100 text-green-800',
    [StudentActionType.WAITING_COURSE_OPENING]: 'bg-amber-100 text-amber-800',
    [StudentActionType.ADMISSION_DEFERRAL]: 'bg-yellow-100 text-yellow-800',
    [StudentActionType.ACADEMIC_DROPOUT]: 'bg-red-100 text-red-800',
    [StudentActionType.CAMPUS_TRANSFER]: 'bg-blue-100 text-blue-800',
};

export interface Semester {
    id: number;
    name: string;
    code: string;
    start_date?: string;
    end_date?: string;
}

export interface Campus {
    id: number;
    name: string;
    code: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface UploadRecord {
    id: number;
    filename: string;
    original_name: string;
    mime_type: string;
    size: number;
    url: string;
    public_url: string;
    created_at: string;
}

export interface StudentBasic {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    campus_id?: number;
    campus?: Campus;
}

export interface StudentActionLog {
    id: number;
    student_id: number;
    action_type: StudentActionType;
    reason: string;
    notes?: string;
    signed_at?: string;
    decision_number?: string;
    decision_signed_at?: string;
    decision_signer?: string;
    decision_id?: number | null;
    missing_documents: boolean;
    changed_by_user_id: number;

    // Semester references
    from_semester_id?: number;
    return_semester_id?: number;
    egc_defer_from_block_number?: number | null;
    intended_intake_semester_id?: number;
    dropout_semester_id?: number;
    effective_semester_id?: number;

    // Campus transfer fields
    from_campus_id?: number;
    to_campus_id?: number;
    effective_at?: string;

    // Snapshot
    previous_status?: string;
    new_status?: string;
    previous_campus_id?: number;

    // Timestamps
    created_at: string;
    updated_at: string;

    // Relationships
    student?: StudentBasic;
    changed_by?: User;
    from_semester?: Semester;
    return_semester?: Semester;
    intended_intake_semester?: Semester;
    dropout_semester?: Semester;
    effective_semester?: Semester;
    from_campus?: Campus;
    to_campus?: Campus;
    attachments?: UploadRecord[];
    decision?: {
        id: number;
        decision_name: string;
        decision_number: string;
        decision_signer?: string;
        issued_at?: string;
        expires_at?: string | null;
    } | null;
    defer_case?: {
        scope_type?: 'FULL' | 'COURSES';
    };

    // Computed
    action_type_label?: string;
    action_type_label_en?: string;
    formatted_changed_at?: string;
    formatted_signed_at?: string;
    summary?: string;
}

export interface StoreStudentActionForm {
    student_id: number;
    action_type: StudentActionType | '';
    reason: string;
    notes?: string;
    signed_at?: string;
    decision_number?: string;
    decision_signed_at?: string;
    decision_signer?: string;
    decision_id?: number | null;
    missing_documents: boolean;
    attachment_ids?: number[];

    // ACADEMIC_DEFER
    from_semester_id?: number | null;
    return_semester_id?: number | null;
    egc_defer_from_block_number?: number | null;

    // ADMISSION_DEFERRAL
    intended_intake_semester_id?: number | null;

    // ACADEMIC_DROPOUT
    dropout_semester_id?: number | null;

    // CAMPUS_TRANSFER
    from_campus_id?: number | null;
    to_campus_id?: number | null;
    effective_at?: string;
    effective_semester_id?: number | null;

    // Defer Case fields (for ACADEMIC_DEFER)
    defer_scope_type?: 'FULL' | 'COURSES' | null;
    defer_fee_policy?: 'PRESERVE' | 'FORFEIT' | 'PARTIAL' | null;
    defer_preserve_amount?: number | null;
    defer_course_registration_ids?: number[];
    defer_egc_charge_ids?: number[];
}

export interface CourseRegistrationPreview {
    id: number;
    course_code: string;
    course_name: string;
    semester_name: string;
    semester_id?: number;
    registration_status?: string;
}

export interface EgcChargePreview {
    id: number;
    semester_id: number | null;
    amount: number | string;
    description?: string | null;
    effective_at?: string | null;
    paid_amount?: number | string;
    is_fully_paid?: boolean;
}

export interface StudentActionFilters {
    action_type?: string | null;
    date_from?: string | null;
    date_to?: string | null;
    signed_date_from?: string | null;
    signed_date_to?: string | null;
    semester_id?: number | null;
    from_semester_id?: number | null;
    egc_defer_from_block_number?: number | null;
    campus_id?: number | null;
    to_campus_id?: number | null;
    from_campus_id?: number | null;
    actor_id?: number | null;
    missing_documents?: string | null;
    search?: string | null;
    sort?: string;
    direction?: 'asc' | 'desc';
    per_page?: number;
}

export interface ActionTypeOption {
    value: string;
    label: string;
    labelEn: string;
    description: string;
}

export interface EgcDeferBlockOption {
    value: number;
    label: string;
}

export function getActionTypeLabel(actionType: string): string {
    return STUDENT_ACTION_TYPE_LABELS[actionType as StudentActionType] ?? actionType;
}

export function getActionTypeLabelEn(actionType: string): string {
    return STUDENT_ACTION_TYPE_LABELS_EN[actionType as StudentActionType] ?? actionType;
}

export function getActionTypeBadgeClass(actionType: string): string {
    return STUDENT_ACTION_TYPE_BADGE_CLASSES[actionType as StudentActionType] ?? 'bg-gray-100 text-gray-800';
}

/**
 * Get the required fields for a given action type
 */
export function getRequiredFieldsForActionType(actionType: StudentActionType): string[] {
    switch (actionType) {
        case StudentActionType.ACADEMIC_DEFER:
            return ['from_semester_id', 'return_semester_id'];
        case StudentActionType.ACADEMIC_RESUME:
            return ['return_semester_id'];
        case StudentActionType.WAITING_COURSE_OPENING:
            return ['from_semester_id', 'egc_defer_from_block_number'];
        case StudentActionType.ADMISSION_DEFERRAL:
            return ['intended_intake_semester_id'];
        case StudentActionType.ACADEMIC_DROPOUT:
            return ['dropout_semester_id'];
        case StudentActionType.CAMPUS_TRANSFER:
            return ['from_campus_id', 'to_campus_id', 'effective_at'];
        default:
            return [];
    }
}
