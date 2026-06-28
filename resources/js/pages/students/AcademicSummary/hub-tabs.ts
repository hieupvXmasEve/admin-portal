import { Activity, BookOpen, GraduationCap, Receipt, Target, User, Users, Wallet, type LucideIcon } from 'lucide-vue-next';

/**
 * A single tab in the Student Hub shell.
 */
export interface StudentHubTab {
    key: string;
    label: string;
    icon: LucideIcon;
    /** Laravel/Ziggy route name resolved with the student id. */
    route: string;
    /** Optional permission gate; when set, the tab renders only if the viewer has it. */
    permission?: string;
}

/**
 * Data-driven Student Hub tab registry (ADR-0007).
 *
 * The Hub shell (StudentLayout) renders whatever is listed here, so later
 * slices add a tab by appending one entry — no shell edits required. GPA is
 * merged into the Scores & GPA tab (issue 03), so it is not its own tab.
 * Graduation is enabled (issue 04); it sits after Attendance, before the
 * finance tabs (Fees, Gold), mirroring the PRD tab order.
 */
export const STUDENT_HUB_TABS: StudentHubTab[] = [
    { key: 'overview', label: 'Overview', icon: User, route: 'students.academic-summary.overview' },
    { key: 'registrations', label: 'Registrations', icon: BookOpen, route: 'students.academic-summary.registrations' },
    { key: 'scores', label: 'Scores & GPA', icon: Target, route: 'students.academic-summary.scores' },
    { key: 'attendance', label: 'Attendance', icon: Users, route: 'students.academic-summary.attendance' },
    { key: 'lifecycle', label: 'Lifecycle', icon: Activity, route: 'students.academic-summary.lifecycle', permission: 'view_student_action' },
    { key: 'graduation', label: 'Graduation', icon: GraduationCap, route: 'students.academic-summary.graduation' },
    { key: 'fees', label: 'Fees', icon: Receipt, route: 'students.academic-summary.fees' },
    { key: 'gold', label: 'Gold', icon: Wallet, route: 'students.academic-summary.gold' },
];
