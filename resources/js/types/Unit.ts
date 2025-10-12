export interface UnitData {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    level: number;
    unit_type: string;
    created_at: string;
    updated_at: string;
    equivalent_units: Array<{
        id: number;
        equivalent_unit: {
            id: number;
            code: string;
            name: string;
            credit_points: number;
        };
        reason: string;
        valid_from_semester: {
            year: number;
            term: string;
        };
    }>;
    equivalent_to: Array<{
        id: number;
        unit: {
            id: number;
            code: string;
            name: string;
            credit_points: number;
        };
        reason: string;
        valid_from_semester: {
            year: number;
            term: string;
        };
    }>;
    prerequisite_groups: Array<{
        id: number;
        logic_operator: 'AND' | 'OR';
        description: string;
        conditions: Array<{
            id: number;
            type: string;
            required_unit?: {
                id: number;
                code: string;
                name: string;
                credit_points: number;
            };
            required_credits?: number;
            free_text?: string;
        }>;
    }>;
    curriculum_units: Array<{
        id: number;
        type?: 'core' | 'major' | 'elective';
        is_compulsory: boolean;
        note?: string;
        curriculum_version: {
            id: number;
            version_code: string;
            program: {
                id: number;
                name: string;
            };
            specialization?: {
                id: number;
                name: string;
            };
        };
        semester: {
            id: number;
            term: string;
            year: number;
        };
    }>;
}

export interface FormDefaults {
    code: string;
    name: string;
    credit_points: number;
}

export interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    level: number;
    unit_type: string;
}

export interface PrerequisiteCondition {
    id?: number;
    type: 'prerequisite' | 'co_requisite' | 'concurrent_prerequisite' | 'anti_requisite' | 'assumed_knowledge' | 'credit_requirement';
    required_unit_id?: number;
    unit?: Unit;
    required_credits?: number;
    free_text?: string;
}

export interface PrerequisiteGroup {
    id?: number;
    logic_operator: 'AND' | 'OR';
    description?: string;
    conditions: PrerequisiteCondition[];
}

export interface EquivalentUnit {
    unit: Unit;
    reason?: string;
}

export interface CurriculumUnit {
    id: number;
    curriculum_version_id: number;
    unit_id: number;
    semester_id: number;
    type: 'core' | 'major' | 'elective';
    semester_number?: number;
    is_compulsory: boolean;
    note?: string;
    created_at: string;
    updated_at: string;
    unit?: Unit;
    semester?: {
        id: number;
        name: string;
        code: string;
    };
    curriculumVersion?: {
        id: number;
        version_code: string;
        program?: {
            id: number;
            name: string;
            code?: string;
        };
        specialization?: {
            id: number;
            name: string;
            code?: string;
        };
    };
}
