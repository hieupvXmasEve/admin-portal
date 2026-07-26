// Worked Metropolia examples documented in
// docs/features/academic/grading.md. Applying one seeds both
// the Assessment Components (single source of truth) and the grading scheme, so
// staff start from a real, runnable rule rather than a blank form.

import type { GradingScheme } from '@/types/grading-scheme';

export type ExampleComponentType = 'quiz' | 'assignment' | 'project' | 'exam' | 'online_activity' | 'attendance' | 'other';

export interface ExampleComponent {
    name: string;
    code: string;
    type: ExampleComponentType;
    weight: number;
}

export interface GradingSchemeExample {
    id: string;
    title: string;
    description: string;
    engineLabel: string;
    assessmentComponents: ExampleComponent[];
    scheme: GradingScheme;
}

export const GRADING_SCHEME_EXAMPLES: GradingSchemeExample[] = [
    {
        id: 'software1-programming',
        title: 'Software 1 — Programming',
        description: 'Exam quy đổi tuyến tính 40% → điểm 1, 88% → điểm 5. Bài tập là điều kiện đạt môn (≥ 40%), không cộng điểm.',
        engineLabel: 'Cộng điểm quy đổi (0–5)',
        assessmentComponents: [
            { name: 'Assignments', code: 'ASSIGNMENT', type: 'assignment', weight: 0 },
            { name: 'Exam', code: 'EXAM', type: 'exam', weight: 100 },
        ],
        scheme: {
            engine: 'metropolia_v1',
            version: 1,
            scale: '0-5',
            components: [
                { code: 'ASSIGNMENT', label: 'Assignments', gate: { min_pct: 40 } },
                {
                    code: 'EXAM',
                    label: 'Exam',
                    gate: { min_pct: 40 },
                    conversion: { type: 'linear', min_pct: 40, max_pct: 88, min_grade: 1, max_grade: 5 },
                },
            ],
        },
    },
    {
        id: 'database-pass-fail',
        title: 'Database — Pass/Fail',
        description: 'Chỉ chấm Đạt/Không đạt: tổng điểm bài tập phải ≥ 80% để đạt môn.',
        engineLabel: 'Cộng điểm quy đổi (Đạt/Không đạt)',
        assessmentComponents: [{ name: 'Assignments', code: 'ASSIGNMENT', type: 'assignment', weight: 100 }],
        scheme: {
            engine: 'metropolia_v1',
            version: 1,
            scale: 'pass_fail',
            components: [{ code: 'ASSIGNMENT', label: 'Assignments', conversion: { type: 'pass_fail', min_pct: 80 } }],
        },
    },
    {
        id: 'maths-physics-threshold',
        title: 'Maths & Physics — Bậc thang',
        description: 'Điểm cuối = điểm Bài tập + điểm Thi, mỗi phần quy đổi theo mốc bậc thang.',
        engineLabel: 'Cộng điểm quy đổi (0–5)',
        assessmentComponents: [
            { name: 'Assignments', code: 'ASSIGNMENT', type: 'assignment', weight: 50 },
            { name: 'Final Exam', code: 'EXAM', type: 'exam', weight: 50 },
        ],
        scheme: {
            engine: 'metropolia_v1',
            version: 1,
            scale: '0-5',
            components: [
                {
                    code: 'ASSIGNMENT',
                    label: 'Assignments',
                    conversion: {
                        type: 'threshold',
                        steps: [
                            { min_pct: 55, grade: 1 },
                            { min_pct: 70, grade: 2 },
                        ],
                    },
                },
                {
                    code: 'EXAM',
                    label: 'Final Exam',
                    conversion: {
                        type: 'threshold',
                        steps: [
                            { min_pct: 50, grade: 1 },
                            { min_pct: 70, grade: 2 },
                            { min_pct: 85, grade: 3 },
                        ],
                    },
                },
            ],
        },
    },
    {
        id: 'cloud-computing-formula',
        title: 'Cloud Computing — Công thức',
        description: 'Labs 50% + Quizzes 30% + Final Exam 20%. Trọng số nhúng vào công thức (biến là % thô 0–100).',
        engineLabel: 'Công thức tổng (0–5)',
        assessmentComponents: [
            { name: 'Labs', code: 'LAB', type: 'assignment', weight: 50 },
            { name: 'Quizzes', code: 'QUIZ', type: 'quiz', weight: 30 },
            { name: 'Final Exam', code: 'EXAM', type: 'exam', weight: 20 },
        ],
        scheme: {
            engine: 'metropolia_v2',
            version: 1,
            scale: '0-5',
            // Source rule (weighted points): (lab/50 + quiz/30 + exam/20 ... ).
            // Swinx feeds raw 0–100 percentages, so the weights are baked into the
            // coefficients: 0.5*LAB + 0.3*QUIZ + (0.2*EXAM)/2 = 0.1*EXAM.
            formula: '(0.5*LAB + 0.3*QUIZ + 0.1*EXAM - 40) / 10',
            rounding_stage: 'after_total',
            clamp_min: 0,
            clamp_max: 5,
            components: [
                { code: 'LAB', label: 'Labs' },
                { code: 'QUIZ', label: 'Quizzes' },
                { code: 'EXAM', label: 'Final Exam' },
            ],
        },
    },
    {
        id: 'health-technology-formula',
        title: 'Health Technology — Công thức',
        description: 'Không thi: điểm cuối = (ASSIGNMENT − 40) / 10 trên tổng % bài tập.',
        engineLabel: 'Công thức tổng (0–5)',
        assessmentComponents: [{ name: 'Assignments', code: 'ASSIGNMENT', type: 'assignment', weight: 100 }],
        scheme: {
            engine: 'metropolia_v2',
            version: 1,
            scale: '0-5',
            formula: '(ASSIGNMENT - 40) / 10',
            rounding_stage: 'after_total',
            clamp_min: 0,
            clamp_max: 5,
            components: [{ code: 'ASSIGNMENT', label: 'Assignments' }],
        },
    },
];
