export enum StudentStatus {
    Active = 'active',
    Inactive = 'inactive',
    Suspended = 'suspended',
    Graduated = 'graduated',
    IntakePreUniGC = 'intake_pre_uni_gc',
    IntakeCourse = 'intake_course',
    Deferred = 'deferred',
    Dropout = 'dropout',
    DropoutTransfer = 'dropout_transfer',
    Pending = 'pending',
}

export const STUDENT_STATUS_LABELS: Record<StudentStatus, string> = {
    [StudentStatus.Active]: 'Active',
    [StudentStatus.Inactive]: 'Inactive',
    [StudentStatus.Suspended]: 'Suspended',
    [StudentStatus.Graduated]: 'Graduated',
    [StudentStatus.IntakePreUniGC]: 'Intake Pre-Uni GC',
    [StudentStatus.IntakeCourse]: 'Intake Course',
    [StudentStatus.Deferred]: 'Deferred',
    [StudentStatus.Dropout]: 'Dropout',
    [StudentStatus.DropoutTransfer]: 'Dropout Transfer',
    [StudentStatus.Pending]: 'Pending',
};

// Tailwind badge classes
export const STUDENT_STATUS_BADGE_CLASSES: Record<StudentStatus, string> = {
    [StudentStatus.Active]: 'bg-green-100 text-green-800',
    [StudentStatus.Inactive]: 'bg-gray-100 text-gray-800',
    [StudentStatus.Suspended]: 'bg-red-100 text-red-800',
    [StudentStatus.Graduated]: 'bg-blue-100 text-blue-800',
    [StudentStatus.IntakePreUniGC]: 'bg-yellow-100 text-yellow-800',
    [StudentStatus.IntakeCourse]: 'bg-indigo-100 text-indigo-800',
    [StudentStatus.Deferred]: 'bg-orange-100 text-orange-800',
    [StudentStatus.Dropout]: 'bg-red-100 text-red-800',
    [StudentStatus.DropoutTransfer]: 'bg-red-100 text-red-800',
    [StudentStatus.Pending]: 'bg-yellow-100 text-yellow-800',
};

export function getStudentStatusLabel(status: string): string {
    const s = status as StudentStatus;
    return STUDENT_STATUS_LABELS[s] ?? status;
}

export function getStudentStatusBadgeClass(status: string): string {
    const s = status as StudentStatus;
    return STUDENT_STATUS_BADGE_CLASSES[s] ?? 'bg-gray-100 text-gray-800';
}

// Status descriptions for tooltips
export const STUDENT_STATUS_DESCRIPTIONS: Record<StudentStatus, string> = {
    [StudentStatus.Pending]: 'Chưa nhập học, chưa hoàn tất hồ sơ',
    [StudentStatus.IntakePreUniGC]: 'Đang học GC, chưa vào khóa chính',
    [StudentStatus.IntakeCourse]: 'Đang ở trạng thái mới nhập vào chương trình chính',
    [StudentStatus.Active]: 'Đang học bình thường',
    [StudentStatus.Inactive]: 'Ngừng tạm thời, chưa bỏ hẳn',
    [StudentStatus.Suspended]: 'Bị đình chỉ/kỷ luật',
    [StudentStatus.Deferred]: 'Sinh viên xin hoãn học có lý do, có dự kiến quay lại',
    [StudentStatus.Dropout]: 'Bỏ học hẳn, không quay lại',
    [StudentStatus.DropoutTransfer]: 'Bỏ vì chuyển sang nơi khác',
    [StudentStatus.Graduated]: 'Tốt nghiệp',
};

export function getStudentStatusDescription(status: string): string {
    const s = status as StudentStatus;
    return STUDENT_STATUS_DESCRIPTIONS[s] ?? '';
}

export enum EmergencyContactStatus {
    DAD = 'dad',
    MOM = 'mom',
    BROTHER = 'brother',
    SISTER = 'sister',
    GUARDIAN = 'guardian',
    SPOUSE = 'spouse',
    GRANDPARENT = 'grandparent',
    UNCLE = 'uncle',
    AUNT = 'aunt',
    COUSIN = 'cousin',
    OTHER = 'other',
}
export const relationshipOptions = Object.entries(EmergencyContactStatus).map(([key, value]) => ({
    label: key.charAt(0).toUpperCase() + key.slice(1).toLowerCase(), // Ví dụ: "DAD" → "Dad"
    value,
}));
