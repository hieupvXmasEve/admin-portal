/**
 * Operational-state contract for the Course Offering Cockpit (ADR 0013).
 * Mirrors app/Modules/Academic/Queries/GetCourseOfferingOperationalStateQuery.php —
 * the backend derives lifecycle, blockers, and actions; the frontend only renders.
 */

export type LifecycleStage = 'setup' | 'registration' | 'teaching' | 'grading' | 'completed' | 'cancelled';

export interface ReadinessBlockerReference {
    type: string;
    id: number;
    label: string;
}

export interface ReadinessBlocker {
    code: string;
    message: string;
    references: ReadinessBlockerReference[];
}

export interface AvailableAction {
    action: string;
    label: string;
    allowed: boolean;
    blocked_by: string[];
}

export interface OperationalState {
    lifecycle_stage: LifecycleStage;
    session_progress: { total: number; completed: number };
    readiness_blockers: ReadinessBlocker[];
    available_actions: AvailableAction[];
}
