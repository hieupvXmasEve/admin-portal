// Shared admin types for the syllabus grading scheme editor/preview (S-003, S-007).
// Mirrors the executable contract in app/Modules/Academic/Support/Grading.

export type GradingSchemeEngine = 'default' | 'metropolia_v1' | 'metropolia_v2';
export type GradingSchemeScale = '0-5' | 'pass_fail';

// metropolia_v1 per-component conversion vocabulary (MetropoliaV1Calculator).
export type GradingConversionType = 'linear' | 'threshold' | 'direct' | 'pass_fail';

export interface LinearConversion {
    type: 'linear';
    min_pct: number;
    max_pct: number;
    min_grade: number;
    max_grade: number;
}

export interface ThresholdStep {
    min_pct: number;
    grade: number;
}

export interface ThresholdConversion {
    type: 'threshold';
    steps: ThresholdStep[];
}

export interface DirectConversion {
    type: 'direct';
    max_grade: number;
}

export interface PassFailConversion {
    type: 'pass_fail';
    min_pct: number;
}

export type GradingConversion = LinearConversion | ThresholdConversion | DirectConversion | PassFailConversion;

export interface GradingSchemeComponent {
    code: string;
    label?: string;
    gate?: { min_pct: number };
    conversion?: GradingConversion;
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
