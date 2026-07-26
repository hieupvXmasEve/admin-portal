// Canonical assessment component codes from
// docs/features/academic/grading.md. Used as the suggestion
// list for the Assessment Component "Code" field so staff pick standard codes
// (custom codes are still allowed via free typing).

export interface MetropoliaComponentCode {
    code: string;
    label: string;
}

export const METROPOLIA_COMPONENT_CODES: MetropoliaComponentCode[] = [
    { code: 'ASSIGNMENT', label: 'Assignments' },
    { code: 'EXAM', label: 'Exam / Final Exam' },
    { code: 'LAB', label: 'Labs' },
    { code: 'QUIZ', label: 'Quizzes' },
    { code: 'PROJECT', label: 'Project Presentation' },
    { code: 'HTML_CSS', label: 'HTML+CSS Assignment' },
    { code: 'JS', label: 'JavaScript Assignments' },
];
