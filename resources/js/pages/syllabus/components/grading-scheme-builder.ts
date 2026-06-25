// Pure serialize/deserialize between the Metropolia builder's form state and the
// executable grading scheme contract (S-007). Single-source design: the scheme
// components are derived from the template's Assessment Components (passed in by
// code), so the builder only stores the *grading rule* per code — never the
// component identity itself. Kept framework-free for unit testing.

import type { GradingConversionType, GradingScheme, GradingSchemeScale, ThresholdStep } from '@/types/grading-scheme';

export type BuilderEngine = 'metropolia_v1' | 'metropolia_v2';
export type RowConversionType = GradingConversionType | 'none';

/** One assessment component the scheme can reference (identity lives on the page). */
export interface ComponentRef {
    code: string;
    label: string;
}

/** metropolia_v1 conversion rule for a single component code. */
export interface RowConfig {
    conversionType: RowConversionType;
    linearMinPct: number;
    linearMaxPct: number;
    linearMinGrade: number;
    linearMaxGrade: number;
    steps: ThresholdStep[];
    directMaxGrade: number;
    passFailMinPct: number;
    gateEnabled: boolean;
    gateMinPct: number;
}

export interface BuilderPassRequirement {
    code: string;
    min_pct: number;
}

export interface BuilderState {
    enabled: boolean; // false => default weighted (scheme = null)
    engine: BuilderEngine;
    scale: GradingSchemeScale;
    configByCode: Record<string, RowConfig>;
    formula: string;
    passRequirements: BuilderPassRequirement[];
    clampMin: number;
    clampMax: number;
    rounding: 'after_total' | 'none';
}

export function createRowConfig(overrides: Partial<RowConfig> = {}): RowConfig {
    return {
        conversionType: 'linear',
        linearMinPct: 40,
        linearMaxPct: 88,
        linearMinGrade: 1,
        linearMaxGrade: 5,
        steps: [{ min_pct: 50, grade: 1 }],
        directMaxGrade: 5,
        passFailMinPct: 80,
        gateEnabled: false,
        gateMinPct: 40,
        ...overrides,
    };
}

export function createDefaultBuilderState(): BuilderState {
    return {
        enabled: false,
        engine: 'metropolia_v1',
        scale: '0-5',
        configByCode: {},
        formula: '',
        passRequirements: [],
        clampMin: 0,
        clampMax: 5,
        rounding: 'after_total',
    };
}

/**
 * Build the executable scheme from the form state plus the page's components.
 * Returns null on the default weighted path. Only components with a code take
 * part — codeless components cannot be a formula variable or a conversion target.
 */
export function serializeBuilderState(state: BuilderState, components: ComponentRef[]): GradingScheme | null {
    if (!state.enabled) {
        return null;
    }

    const usable = components.filter((component) => component.code !== '');
    const schemeComponents = usable.map((component) => serializeComponent(component, state));

    if (state.engine === 'metropolia_v2') {
        const declared = new Set(usable.map((component) => component.code));
        const scheme: GradingScheme = {
            engine: 'metropolia_v2',
            version: 1,
            scale: state.scale,
            formula: state.formula,
            rounding_stage: state.rounding,
            clamp_min: state.clampMin,
            clamp_max: state.clampMax,
            components: schemeComponents,
        };

        const requirements = state.passRequirements.filter((requirement) => declared.has(requirement.code));
        if (requirements.length > 0) {
            scheme.pass_requirements = requirements.map((requirement) => ({ code: requirement.code, min_pct: requirement.min_pct }));
        }

        return scheme;
    }

    return {
        engine: 'metropolia_v1',
        version: 1,
        scale: state.scale,
        components: schemeComponents,
    };
}

function serializeComponent(component: ComponentRef, state: BuilderState): GradingScheme['components'][number] {
    const result: GradingScheme['components'][number] = { code: component.code, label: component.label };

    if (state.engine === 'metropolia_v2') {
        return result;
    }

    const config = state.configByCode[component.code] ?? createRowConfig();

    if (config.gateEnabled) {
        result.gate = { min_pct: config.gateMinPct };
    }

    switch (config.conversionType) {
        case 'linear':
            result.conversion = {
                type: 'linear',
                min_pct: config.linearMinPct,
                max_pct: config.linearMaxPct,
                min_grade: config.linearMinGrade,
                max_grade: config.linearMaxGrade,
            };
            break;
        case 'threshold':
            result.conversion = { type: 'threshold', steps: config.steps };
            break;
        case 'direct':
            result.conversion = { type: 'direct', max_grade: config.directMaxGrade };
            break;
        case 'pass_fail':
            result.conversion = { type: 'pass_fail', min_pct: config.passFailMinPct };
            break;
        case 'none':
            break;
    }

    return result;
}

/** Rebuild form state from a stored scheme (Edit page). */
export function deserializeScheme(scheme: GradingScheme | null): BuilderState {
    if (!scheme || (scheme.engine !== 'metropolia_v1' && scheme.engine !== 'metropolia_v2')) {
        return createDefaultBuilderState();
    }

    const state = createDefaultBuilderState();
    state.enabled = true;
    state.engine = scheme.engine;
    state.scale = scheme.scale ?? '0-5';
    state.formula = typeof scheme.formula === 'string' ? scheme.formula : '';
    state.rounding = scheme.rounding_stage === 'none' ? 'none' : 'after_total';
    state.clampMin = typeof scheme.clamp_min === 'number' ? scheme.clamp_min : 0;
    state.clampMax = typeof scheme.clamp_max === 'number' ? scheme.clamp_max : 5;
    state.passRequirements = (scheme.pass_requirements ?? []).map((requirement) => ({ code: requirement.code, min_pct: requirement.min_pct }));

    for (const component of scheme.components ?? []) {
        state.configByCode[component.code] = rowConfigFromComponent(component);
    }

    return state;
}

function rowConfigFromComponent(component: GradingScheme['components'][number]): RowConfig {
    const config = createRowConfig();

    if (component.gate) {
        config.gateEnabled = true;
        config.gateMinPct = component.gate.min_pct;
    }

    const conversion = component.conversion;

    if (!conversion) {
        config.conversionType = 'none';
    } else if (conversion.type === 'linear') {
        config.conversionType = 'linear';
        config.linearMinPct = conversion.min_pct;
        config.linearMaxPct = conversion.max_pct;
        config.linearMinGrade = conversion.min_grade;
        config.linearMaxGrade = conversion.max_grade;
    } else if (conversion.type === 'threshold') {
        config.conversionType = 'threshold';
        config.steps = conversion.steps.length > 0 ? conversion.steps : [{ min_pct: 50, grade: 1 }];
    } else if (conversion.type === 'direct') {
        config.conversionType = 'direct';
        config.directMaxGrade = conversion.max_grade;
    } else if (conversion.type === 'pass_fail') {
        config.conversionType = 'pass_fail';
        config.passFailMinPct = conversion.min_pct;
    }

    return config;
}

/** Derive an UPPER_SNAKE component code suggestion from a free-text name. */
export function suggestCode(name: string): string {
    return name
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '') // strip combining diacritics (e.g. Vietnamese accents)
        .toUpperCase()
        .replace(/[^A-Z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 20);
}
