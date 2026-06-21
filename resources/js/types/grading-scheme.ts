// Shared admin types for the syllabus grading scheme editor/preview (S-003).
// Mirrors the executable contract in app/Modules/Academic/Support/Grading.

export type GradingSchemeEngine = 'default' | 'metropolia_v1' | 'metropolia_v2';
export type GradingSchemeScale = '0-5' | 'pass_fail';

export interface GradingSchemeComponent {
    code: string;
    label?: string;
    gate?: { min_pct: number };
    conversion?: Record<string, unknown>;
}

export interface GradingSchemePassRequirement {
    code: string;
    min_pct: number;
}

export interface GradingScheme {
    engine: Exclude<GradingSchemeEngine, 'default'>;
    version: number;
    scale: GradingSchemeScale;
    source_reference?: string;
    components: GradingSchemeComponent[];
    // metropolia_v2 (formula engine) fields
    formula?: string;
    rounding_stage?: 'after_total' | 'none';
    clamp_min?: number;
    clamp_max?: number;
    pass_requirements?: GradingSchemePassRequirement[];
    [key: string]: unknown;
}

export interface GradingEngineOption {
    value: GradingSchemeEngine;
    label: string;
}

export interface GradingSchemePreviewResult {
    valid: boolean;
    scale?: GradingSchemeScale | null;
    final_grade?: string;
    final_percentage?: number | null;
    grade_points?: number;
    passed?: boolean;
    breakdown?: Record<string, unknown>;
    errors?: string[];
}
