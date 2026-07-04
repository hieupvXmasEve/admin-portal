export interface MetropoliaGateDef {
    min_pct: number;
}

export interface MetropoliaConversionDef {
    type: 'linear' | 'threshold' | 'direct' | 'pass_fail';
    min_pct?: number;
    max_pct?: number;
    min_grade?: number;
    max_grade?: number;
    steps?: Array<{ min_pct: number; grade: number }>;
}

export interface MetropoliaSchemeComponent {
    code: string;
    label?: string;
    gate?: MetropoliaGateDef | null;
    conversion?: MetropoliaConversionDef | null;
}

export interface MetropoliaPassRequirement {
    code: string;
    min_pct: number;
}

export interface MetropoliaScheme {
    engine: string;
    scale: 'numeric_0_5' | 'pass_fail' | 'percentage';
    components?: MetropoliaSchemeComponent[];
    formula?: string | null;
    pass_requirements?: MetropoliaPassRequirement[];
    rounding_stage?: string | null;
    clamp_min?: number | null;
    clamp_max?: number | null;
}

function describeConversion(conversion: MetropoliaConversionDef): string {
    switch (conversion.type) {
        case 'linear':
            return `${conversion.min_pct}% → grade ${conversion.min_grade}, ${conversion.max_pct}% → grade ${conversion.max_grade} (linear)`;
        case 'threshold':
            return (conversion.steps ?? []).map((step) => `≥${step.min_pct}% → grade ${step.grade}`).join(', ');
        case 'direct':
            return `score/100 × ${conversion.max_grade ?? 5}`;
        case 'pass_fail':
            return `pass/fail at ≥${conversion.min_pct}%`;
        default:
            return '';
    }
}

function describeComponent(component: MetropoliaSchemeComponent): string {
    const label = component.label || component.code;
    const parts: string[] = [];

    if (component.gate) {
        parts.push(`requires ≥${component.gate.min_pct}% to pass`);
    }

    if (component.conversion) {
        parts.push(describeConversion(component.conversion));
    } else if (!component.gate) {
        parts.push('no grade contribution (informational only)');
    }

    return `${label}: ${parts.filter(Boolean).join('; ')}`;
}

/**
 * Build human-readable formula lines from a raw Metropolia scheme definition —
 * metropolia_v1 stores per-component gate/conversion rules, metropolia_v2
 * stores a single arithmetic formula string — so staff can see why a student
 * passed/failed without recomputing it themselves.
 */
export function describeMetropoliaScheme(scheme: MetropoliaScheme): string[] {
    if (scheme.engine === 'metropolia_v2') {
        const lines: string[] = [`Final Grade = ${scheme.formula ?? '—'}`];

        (scheme.components ?? []).forEach((component) => {
            lines.push(`${component.code} = ${component.label ?? component.code}`);
        });

        (scheme.pass_requirements ?? []).forEach((requirement) => {
            lines.push(`Requires ${requirement.code} ≥ ${requirement.min_pct}% to pass`);
        });

        const clampMin = scheme.clamp_min ?? 0;
        const clampMax = scheme.clamp_max ?? 5;
        const rounding = scheme.rounding_stage === 'none' ? '' : 'rounded to nearest whole number, ';
        lines.push(`Result is ${rounding}clamped to [${clampMin}, ${clampMax}]`);

        return lines;
    }

    const lines = (scheme.components ?? []).map(describeComponent);
    lines.push('Final Grade = sum of all component grades, rounded to nearest whole number (clamped 0–5)');

    return lines;
}
