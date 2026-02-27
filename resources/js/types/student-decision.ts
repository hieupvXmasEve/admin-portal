export interface StudentDecision {
    id: number;
    decision_name: string;
    decision_number: string;
    decision_signer: string;
    issued_at: string;
    expires_at: string | null;
    upload_record_id: number | null;
    changed_by_user_id: number;
    linked_actions_count?: number;
    linked_students_count?: number;
    total_linked_actions?: number;
    total_linked_students?: number;
    upload_record?: {
        id: number;
        original_name: string;
        url: string;
        public_url: string;
    } | null;
    changed_by?: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    updated_at: string;
}
