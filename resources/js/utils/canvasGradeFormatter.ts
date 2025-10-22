/**
 * Format grade display like Canvas LMS
 * Examples:
 * - "8.5 out of 10 (points)"
 * - "85 out of 100 (manual)" // when grading_type is points
 * - "B+ out of 100 (letter grade)"
 * - "complete out of 100 (pass/fail)"
 */

export interface GradeInfo {
    score: number | null;
    max_points: number;
    grading_type: 'points' | 'percent' | 'letter_grade' | 'gpa_scale' | 'pass_fail' | 'not_graded';
    grade?: string | null; // Letter grade or pass/fail text
}

export function formatCanvasGrade(gradeInfo: GradeInfo): string {
    const { score, max_points, grading_type, grade } = gradeInfo;
    
    // No submission or not graded
    if (score === null || score === undefined) {
        return `— out of ${max_points} (${getGradingTypeLabel(grading_type)})`;
    }
    
    // Format score based on grading type
    let displayScore: string;
    
    switch (grading_type) {
        case 'letter_grade':
            displayScore = grade || score.toString();
            break;
        case 'pass_fail':
            displayScore = score > 0 ? 'complete' : 'incomplete';
            break;
        case 'gpa_scale':
            displayScore = score.toFixed(2);
            break;
        case 'percent':
            displayScore = `${score}%`;
            break;
        case 'not_graded':
            return 'Not graded';
        case 'points':
        default:
            displayScore = score.toString();
            break;
    }
    
    return `${displayScore} out of ${max_points} (${getGradingTypeLabel(grading_type)})`;
}

export function getGradingTypeLabel(gradingType: string): string {
    const labels: Record<string, string> = {
        'points': 'manual', // Canvas shows "manual" for manual grading
        'percent': 'percentage',
        'letter_grade': 'letter grade',
        'gpa_scale': 'GPA scale',
        'pass_fail': 'pass/fail',
        'not_graded': 'not graded',
    };
    
    return labels[gradingType] || gradingType;
}

/**
 * Get submission type labels
 */
export function formatSubmissionTypes(types: string[]): string {
    if (!types || types.length === 0) {
        return 'No submission';
    }
    
    const labels: Record<string, string> = {
        'online_text_entry': 'Text Entry',
        'online_url': 'Website URL',
        'online_upload': 'File Upload',
        'media_recording': 'Media',
        'online_quiz': 'Quiz',
        'external_tool': 'External Tool',
        'student_annotation': 'Student Annotation',
        'none': 'No Submission',
    };
    
    return types.map(type => labels[type] || type).join(', ');
}
