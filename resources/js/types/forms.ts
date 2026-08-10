export interface Form {
    id: number;
    code: string;
    type: 'feedback' | 'survey' | 'query';
    title: string;
    description?: string;
    status: 'draft' | 'active' | 'archived';
    created_by: number;
    creator?: {
        id: number;
        name: string;
        email: string;
    };
    current_version?: FormVersion;
    versions?: FormVersion[];
    latest_version?: number;
    latest_published_version?: FormVersion;
    targets?: FormTarget[];
    visibility_roles?: Role[];
    result_visibility?: FormResultVisibility[];
    statistics?: FormStatistics;
    response_counts?: any;
    can_submit?: boolean;
    submission_count?: number;
    is_available?: boolean;
    has_responses?: boolean;
    active_targets_count?: number;
    aggregate_config?: AggregateConfig | null;
    created_at: string;
    updated_at: string;
    deleted_at?: string;
}

export interface AggregateConfig {
    overall: {
        question_codes: string[];
        thresholds: {
            positive_min: number;
            negative_max: number;
        };
    };
}

export interface FormVersion {
    id: number;
    form_id: number;
    version_no: number;
    is_published: boolean;
    effective_from?: string;
    effective_to?: string;
    sections?: FormSection[];
    questions?: Question[];
    created_at: string;
}

export interface FormSection {
    id: number;
    form_version_id: number;
    title: string;
    description?: string;
    order_index: number;
    questions?: Question[];
}

export interface Question {
    id: number;
    form_version_id: number;
    section_id?: number;
    code: string;
    text: string;
    type: QuestionType;
    is_required: boolean;
    help_text?: string;
    order_index: number;
    validation_json?: any;
    visibility_condition_json?: any;
    options?: Option[];
}

export type QuestionType = 'short_text' | 'long_text' | 'single_choice' | 'multi_choice' | 'rating' | 'date' | 'number' | 'file';

export interface Option {
    id: number;
    question_id: number;
    value: string;
    label: string;
    order_index: number;
    allows_free_text: boolean;
    free_text?: string;
}

export interface FormTarget {
    id: number;
    form_id: number;
    form_version_id?: number;
    campus_id?: number;
    campus_name?: string;
    semester_id?: string | number | null;
    scope_type: ScopeType;
    scope_id?: number;
    start_at: string;
    end_at?: string;
    submission_limit_per_user: number;
    is_mandatory: boolean;
    status: 'draft' | 'active' | 'closed';
    is_active?: boolean;
    // Relationships
    form?: Form;
    semester?: import('./models').Semester;
    form_version?: FormVersion;
}

export type ScopeType = 'section' | 'class_session' | 'course' | 'global';

export interface FormResultVisibility {
    id: number;
    form_id: number;
    role_id: number;
    role_name?: string;
    visibility_level: VisibilityLevel;
    min_aggregation_threshold?: number;
    description?: string;
}

export type VisibilityLevel = 'own_submission' | 'aggregated' | 'full_detail';

export interface FormResponse {
    id: number;
    form_id: number;
    form_version_id: number;
    form?: Form;
    campus_id?: number;
    campus?: {
        id?: number;
        name?: string;
    };
    target_scope_type: ScopeType;
    target_scope_id?: number;
    submitted_by_student_id?: number;
    anonymized: boolean;
    status: ResponseStatus;
    reviewed_by_user_id?: number;
    reviewed_at?: string;
    review_notes?: string;
    origin: 'web' | 'mobile' | 'api';
    submitted_at: string;
    answers?: Answer[];
    attachments?: Attachment[];
    student?: {
        id: number;
        full_name: string;
        student_id: string;
    };
    reviewer?: {
        id: number;
        name: string;
    };
    content_preview?: string;
}

export type ResponseStatus = 'draft' | 'submitted' | 'approved' | 'rejected';

export interface Answer {
    id: number;
    response_id: number;
    question_id: number;
    question?: Question;
    answer_text?: string;
    answer_number?: number;
    answer_date?: string;
    comment?: string;
    selected_options?: Option[];
    attachments?: Attachment[];
    formatted_value?: string;
}

export interface Attachment {
    id: number;
    response_id?: number;
    answer_id?: number;
    storage_key: string;
    file_name: string;
    mime_type: string;
    size_bytes: number;
    uploaded_at: string;
    formatted_size?: string;
    is_image?: boolean;
    is_document?: boolean;
    extension?: string;
    download_url?: string;
}

export interface QueryTicket {
    id: number;
    response_id: number;
    topic_id?: number;
    custom_topic_text?: string;
    status: TicketStatus;
    priority: TicketPriority;
    assigned_to_user_id?: number;
    created_at: string;
    updated_at: string;
    closed_at?: string;
    can_reply?: boolean;
    response?: FormResponse;
    topic?: QueryTopic;
    assigned_to?: {
        id: number;
        name: string;
    };
    replies?: QueryReply[];
}

export type TicketStatus = 'open' | 'pending' | 'answered' | 'closed';
export type TicketPriority = 'low' | 'normal' | 'high';

export interface QueryTopic {
    id: number;
    title: string;
    description?: string;
    is_active: boolean;
    order_index: number;
}

export interface QueryReply {
    id: number;
    ticket_id: number;
    author_user_id?: number;
    author_student_id?: number;
    message: string;
    is_official_answer: boolean;
    upload_record_id?: number;
    created_at: string;
    author?: {
        type: 'student' | 'staff';
        id: number;
        name: string;
        student_id?: string;
    };
    attachment?: ReplyAttachment;
}

export interface ReplyAttachment {
    id: number;
    file_name: string;
    filename: string;
    size: number;
    size_bytes?: number;
    mime_type: string;
    download_url: string;
}

export interface Role {
    id: number;
    code: string;
    name: string;
}

export interface Campus {
    id: number;
    name: string;
    code: string;
    address?: string;
}

export interface FormStatistics {
    total_responses: number;
    submitted: number;
    approved: number;
    rejected: number;
    pending_review: number;
    anonymous_responses: number;
}

// Form Builder Types
export interface FormBuilderSection {
    id?: number;
    title: string;
    description?: string;
    order_index: number;
    questions: FormBuilderQuestion[];
}

export interface FormBuilderQuestion {
    id?: number;
    code: string;
    text: string;
    type: QuestionType;
    is_required: boolean;
    help_text?: string;
    order_index: number;
    validation_json?: QuestionValidation;
    visibility_condition_json?: any;
    options?: FormBuilderOption[];
}

export interface FormBuilderOption {
    id?: number;
    value: string;
    label: string;
    order_index: number;
    allows_free_text: boolean;
}

export interface QuestionValidation {
    maxLength?: number;
    minLength?: number;
    min?: number;
    max?: number;
    decimals?: number;
    rows?: string[];
    cols?: string[] | number[];
}

// API Response Types
export interface FormsIndexResponse {
    data: Form[];
    meta: {
        total: number;
        filters: any;
    };
}

export interface FormDetailResponse {
    data: Form;
}

export interface FormMetadataResponse {
    data: {
        roles: Role[];
        campuses: Campus[];
        query_topics: QueryTopic[];
        question_types: Record<string, string>;
        visibility_levels: Record<string, string>;
        scope_types: Record<string, string>;
        form_types: Record<string, string>;
        form_statuses: Record<string, string>;
    };
}
