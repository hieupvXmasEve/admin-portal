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
