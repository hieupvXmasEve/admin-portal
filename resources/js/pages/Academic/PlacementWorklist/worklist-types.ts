export interface WorklistProgram {
    id: number;
    name: string;
    code: string | null;
}

export interface WorklistSemester {
    id: number;
    name: string;
    code: string | null;
}

export interface WorklistStudent {
    id: number;
    student_id: string;
    full_name: string;
    email: string | null;
    program: WorklistProgram | null;
    intake_semester: WorklistSemester | null;
    admission_date: string | null;
}

export interface PlacementOptions {
    eventTypes: { value: string; label: string }[];
    triggerSources: { value: string; label: string }[];
    semesters: WorklistSemester[];
    englishLevels: { value: number; label: string }[];
    ieltsScoreThreshold: number;
}
