/** One student row in the lifecycle status table, shared by the page and the drill-down dialog. */
export interface StatusRow {
    id: number;
    student_id: string;
    full_name: string;
    program_name: string | null;
    intake_semester: string | null;
    intake_year: number | null;
    current_campus: string | null;
    status_at_selected_semester: string;
    current_status: string;
    latest_action_type: string | null;
    latest_action_effective_semester: string | null;
    ne: boolean;
    defer_start_semester: string | null;
    dropout_semester: string | null;
    updated_at: string | null;
}
