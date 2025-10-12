import type { NavItem } from '@/types';
import {
    Award,
    BarChart3,
    BookMarked,
    BookOpen,
    Building2,
    Calculator,
    Calendar,
    Calendar as CalendarIcon,
    CheckSquare,
    ClipboardCheck,
    ClipboardList,
    Clock,
    Club,
    DollarSign,
    DoorOpen,
    FileText,
    FormInput,
    GraduationCap,
    Receipt,
    Layers,
    LayoutDashboard,
    Mail,
    MailPlus,
    School,
    Settings,
    Settings2,
    ShieldCheck,
    Ticket,
    User,
    UserPlus,
    Users,
} from 'lucide-vue-next';

import { attendanceRoutes, courseRoutes, curriculumRoutes, lecturerRoutes, studentRoutes, systemRoutes } from '@/utils/routes';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
        // requiredPermissions: ['view_dashboard'],
    },
    {
        title: 'System Management',
        href: '#',
        icon: Settings,
        children: [
            {
                title: 'Users',
                href: systemRoutes.users.index(),
                icon: Users,
                requiredPermissions: ['view_user'],
            },
            {
                title: 'Roles & Permissions',
                href: systemRoutes.roles.index(),
                icon: ShieldCheck,
                requiredPermissions: ['view_role'],
            },
            {
                title: 'Campuses & Departments',
                href: systemRoutes.campuses.index(),
                icon: Building2,
                requiredPermissions: ['view_campus'], // Will be implemented later
            },
            {
                title: 'Room Management',
                href: systemRoutes.rooms.index(),
                icon: DoorOpen,
                requiredPermissions: ['view_room'],
            },
        ],
    },
    {
        title: 'Curriculum & Courses',
        href: '#',
        icon: BookOpen,
        children: [
            {
                title: 'Academic Terms',
                href: systemRoutes.semesters.index(),
                icon: Calendar,
                requiredPermissions: ['view_semester'],
            },
            {
                title: 'Programs',
                href: curriculumRoutes.programs.index(),
                requiredPermissions: ['view_program'],
            },
            // {
            //     title: 'Specializations',
            //     href: curriculumRoutes.specializations.index(),
            //     requiredPermissions: ['view_specialization'],
            // },
            {
                title: 'Curriculum Versions',
                href: curriculumRoutes.curriculumVersions.index(),
                icon: Layers,
                requiredPermissions: ['view_curriculum_version'],
            },
            {
                title: 'Units',
                href: curriculumRoutes.units.index(),
                icon: BookMarked,
                requiredPermissions: ['view_unit'],
            },
            {
                title: 'Syllabus Templates',
                href: curriculumRoutes.syllabusTemplates.index(),
                icon: FileText,
                requiredPermissions: ['view_syllabus'],
            },
            // {
            //     title: 'Equivalent/Substitute Courses',
            //     href: curriculumRoutes.equivalentCourses(),
            //     icon: RefreshCw,
            //     requiredPermissions: ['view_unit'], // Will be implemented later
            // },
            // {
            //     title: 'Curriculum Structure',
            //     href: curriculumRoutes.curriculumStructure(),
            //     icon: Layers,
            //     requiredPermissions: ['view_curriculum_version'], // Will be implemented later
            // },
        ],
    },
    {
        title: 'Student Management',
        href: '#',
        icon: User,
        children: [
            {
                title: 'Student List',
                href: studentRoutes.list(),
                icon: Users,
                requiredPermissions: ['view_student'],
            },
            // {
            //     title: 'New Students',
            //     href: studentRoutes.newStudents(),
            //     icon: UserPlus,
            //     requiredPermissions: ['view_student'],
            // },
            {
                title: 'Enrollments & Holds',
                href: studentRoutes.enrollments(), // Placeholder
                icon: ClipboardList,
                requiredPermissions: ['view_student'], // Will be implemented later
            },
            {
                title: 'Academic Records',
                href: studentRoutes.academicRecords(), // Placeholder
                icon: FileText,
                requiredPermissions: ['view_student'], // Will be implemented later
            },
            // {
            //     title: 'Program/Specialization Change',
            //     href: '#', // Placeholder
            //     icon: RefreshCw,
            //     requiredPermissions: ['edit_student'], // Will be implemented later
            // },
            // {
            //     title: 'Repeat/Retake Courses',
            //     href: '#', // Placeholder
            //     icon: Repeat,
            //     requiredPermissions: ['edit_student'], // Will be implemented later
            // },
            // {
            //     title: 'Academic Standing',
            //     href: '#', // Placeholder
            //     icon: TrendingUp,
            //     requiredPermissions: ['view_student'], // Will be implemented later
            // },

            // {
            //     title: 'Student Status Tracking',
            //     href: studentRoutes.statusTracking(),
            //     icon: UserCheck,
            //     requiredPermissions: ['view_student'], // Will be implemented later
            // },
            {
                title: 'Student Applications',
                href: '/student-applications',
                icon: ClipboardCheck,
                requiredPermissions: ['view_student_application'],
            },
        ],
    },
    {
        title: 'Lecturer Management',
        href: '#',
        icon: GraduationCap,
        children: [
            {
                title: 'Lecturer List',
                href: lecturerRoutes.index(),
                icon: Users,
                requiredPermissions: ['view_lecturer'], // Will be implemented later
            },
            // {
            //     title: 'Teaching Assignments',
            //     href: '/teaching-assignments',
            //     icon: BookOpen,
            //     requiredPermissions: ['view_teaching_assignment'],
            // },
            // {
            //     title: 'Lecturer Timetable',
            //     href: lecturerRoutes.index(), // Placeholder
            //     icon: CalendarIcon,
            //     requiredPermissions: ['view_lecturer'], // Will be implemented later
            // },
        ],
    },

    {
        title: 'Course Offerings & Registration',
        href: '#',
        icon: School,
        children: [
            {
                title: 'Course Offering List',
                href: courseRoutes.offerings.index(),
                icon: BookOpen,
                requiredPermissions: ['view_course_offering'],
            },
            // {
            //     title: 'Room & Instructor Assignment',
            //     href: courseRoutes.roomAssignment(),
            //     icon: MapPin,
            //     requiredPermissions: ['edit_course_offering'], // Will be implemented later
            // },
            {
                title: 'Class Schedule',
                href: courseRoutes.classSchedule(),
                icon: CalendarIcon,
                requiredPermissions: ['view_course_offering'], // Will be implemented later
            },
            {
                title: 'Course Registration',
                href: courseRoutes.registrations.index(),
                icon: UserPlus,
                requiredPermissions: ['view_course_registration'],
            },
            // {
            //     title: 'Enrollment Summary',
            //     href: courseRoutes.enrollmentSummary(),
            //     icon: BarChart3,
            //     requiredPermissions: ['view_course_registration'], // Will be implemented later
            // },
        ],
    },
    {
        title: 'Attendance Management',
        href: '#',
        icon: CheckSquare,
        children: [
            {
                title: 'Class Sessions',
                href: attendanceRoutes.classSessions.index(),
                icon: CalendarIcon,
                requiredPermissions: ['view_attendance'], // Will be implemented later
            },
            // {
            //     title: 'Take Attendance',
            //     href: attendanceRoutes.attendance.create(),
            //     icon: CheckSquare,
            //     requiredPermissions: ['edit_attendance'], // Will be implemented later
            // },
            {
                title: 'Attendance Reports',
                href: attendanceRoutes.attendance.index(),
                icon: FileText,
                requiredPermissions: ['view_attendance'], // Will be implemented later
            },
            // {
            //     title: 'GPS & Method Tracking',
            //     href: attendanceRoutes.attendance.index(),
            //     icon: MapPin,
            //     requiredPermissions: ['view_attendance'], // Will be implemented later
            // },
        ],
    },
    {
        title: 'Forms Queries & Feedback',
        href: '#',
        icon: FormInput,
        children: [
            {
                title: 'Form Management',
                href: '/forms/admin',
                icon: FormInput,
                requiredPermissions: ['view_form'], // Will be implemented later
            },
            {
                title: 'Queries',
                href: '/forms/queries',
                icon: ClipboardCheck,
                requiredPermissions: ['review_form'], // Will be implemented later
            },
            // {
            //     title: 'Analytics',
            //     href: '/forms/analytics',
            //     icon: BarChart3,
            //     requiredPermissions: ['view_form_analytics'], // Will be implemented later
            // },
        ],
    },
    // {
    //     title: 'Assessments & Grading',
    //     href: '#',
    //     icon: Star,
    //     children: [
    //         {
    //             title: 'Assessment Components',
    //             href: assessmentRoutes.components(),
    //             icon: Target,
    //             requiredPermissions: ['view_assessment'], // Will be implemented later
    //         },
    //         {
    //             title: 'Enter Grades',
    //             href: assessmentRoutes.enterGrades(),
    //             icon: Star,
    //             requiredPermissions: ['edit_grade'], // Will be implemented later
    //         },
    //         {
    //             title: 'Academic Results',
    //             href: assessmentRoutes.academicResults(),
    //             icon: Trophy,
    //             requiredPermissions: ['view_grade'], // Will be implemented later
    //         },
    //         {
    //             title: 'Re-assessments',
    //             href: assessmentRoutes.reAssessments(),
    //             icon: Repeat,
    //             requiredPermissions: ['edit_assessment'], // Will be implemented later
    //         },
    //         {
    //             title: 'Semester Grade Reports',
    //             href: assessmentRoutes.gradeReports(),
    //             icon: FileSpreadsheet,
    //             requiredPermissions: ['view_grade'], // Will be implemented later
    //         },
    //         {
    //             title: 'GPA History',
    //             href: assessmentRoutes.gpaHistory(),
    //             icon: TrendingUp,
    //             requiredPermissions: ['view_grade'], // Will be implemented later
    //         },
    //     ],
    // },
    // `{
    //     title: 'Academic Summary',
    //     href: '#',
    //     icon: Presentation,
    //     children: [
    //         {
    //             title: 'GPA Calculations',
    //             href: academicSummaryRoutes.gpaCalculations(),
    //             icon: Target,
    //             requiredPermissions: ['view_grade'], // Will be implemented later
    //         },
    //         {
    //             title: 'Degree Classification',
    //             href: academicSummaryRoutes.degreeClassification(),
    //             icon: Award,
    //             requiredPermissions: ['view_grade'], // Will be implemented later
    //         },
    //         {
    //             title: 'Transcript History',
    //             href: academicSummaryRoutes.transcriptHistory(),
    //             icon: FileText,
    //             requiredPermissions: ['view_grade'], // Will be implemented later
    //         },
    //         {
    //             title: 'Academic Performance Report',
    //             href: academicSummaryRoutes.performanceReport(),
    //             icon: LineChart,
    //             requiredPermissions: ['view_grade'], // Will be implemented later
    //         },
    //     ],
    // },`
    // {
    //     title: 'Program Transfers & Course Retakes',
    //     href: '#',
    //     icon: ArrowUpRight,
    //     children: [
    //         {
    //             title: 'Program Change Requests',
    //             href: transferRoutes.programChangeRequests(),
    //             icon: RefreshCw,
    //             requiredPermissions: ['edit_student'], // Will be implemented later
    //         },
    //         {
    //             title: 'Repeated Courses',
    //             href: transferRoutes.repeatedCourses(),
    //             icon: Repeat,
    //             requiredPermissions: ['view_student'], // Will be implemented later
    //         },
    //         {
    //             title: 'Substitute Course Mapping',
    //             href: transferRoutes.substituteCourseMapping(),
    //             icon: BookMarked,
    //             requiredPermissions: ['view_unit'], // Will be implemented later
    //         },
    //         {
    //             title: 'Credit Transfer Evaluation',
    //             href: transferRoutes.creditTransferEvaluation(),
    //             icon: CheckSquare,
    //             requiredPermissions: ['edit_student'], // Will be implemented later
    //         },
    //     ],
    // },
    // {
    //     title: 'Reports & Analytics',
    //     href: '#',
    //     icon: BarChart3,
    //     children: [
    //         {
    //             title: 'Student Statistics',
    //             href: reportRoutes.studentStatistics(),
    //             icon: Users,
    //             requiredPermissions: ['view_report'], // Will be implemented later
    //         },
    //         {
    //             title: 'Academic Performance Summary',
    //             href: reportRoutes.performanceSummary(),
    //             icon: TrendingUp,
    //             requiredPermissions: ['view_report'], // Will be implemented later
    //         },
    //         {
    //             title: 'Attendance Summary',
    //             href: reportRoutes.attendanceSummary(),
    //             icon: Clock,
    //             requiredPermissions: ['view_report'], // Will be implemented later
    //         },
    //         {
    //             title: 'GPA Distribution Charts',
    //             href: reportRoutes.gpaDistribution(),
    //             icon: PieChart,
    //             requiredPermissions: ['view_report'], // Will be implemented later
    //         },
    //     ],
    // },
    // {
    //     title: 'Course Syllabus & Content',
    //     href: '#',
    //     icon: FileText,
    //     children: [
    //         {
    //             title: 'Syllabi Management',
    //             href: '#', // Will use dynamic routes based on unit
    //             icon: FileText,
    //             requiredPermissions: ['view_syllabus'],
    //         },
    //         {
    //             title: 'Learning Materials',
    //             href: syllabusRoutes.learningMaterials(),
    //             icon: BookOpen,
    //             requiredPermissions: ['view_syllabus'], // Will be implemented later
    //         },
    //         {
    //             title: 'Course Learning Outcomes',
    //             href: syllabusRoutes.learningOutcomes(),
    //             icon: Target,
    //             requiredPermissions: ['view_syllabus'], // Will be implemented later
    //         },
    //         {
    //             title: 'Assessment Rubrics',
    //             href: syllabusRoutes.assessmentRubrics(),
    //             icon: CheckSquare,
    //             requiredPermissions: ['view_syllabus'], // Will be implemented later
    //         },
    //     ],
    // },
    {
        title: 'Events',
        href: '#',
        icon: Calendar,
        children: [
            {
                title: 'List',
                href: systemRoutes.events.index(),
                icon: Calendar,
                requiredPermissions: ['view_event'],
            },
            {
                title: 'Reports & Analytics',
                href: systemRoutes.events.reports(),
                icon: BarChart3,
                requiredPermissions: ['view_event'],
            },
        ],
    },
    {
        title: 'Fee',
        href: '#',
        icon: DollarSign,
        children: [
            {
                title: 'Billing Cycles',
                href: '/billing-cycles',
                icon: Clock,
                requiredPermissions: ['view_billing_cycle'],
            },
            {
                title: 'Invoices',
                href: '/invoices',
                icon: Receipt,
                requiredPermissions: ['view_invoice'],
            },
            {
                title: 'Tuition Plans',
                href: '/tuition-plans',
                icon: Calculator,
                requiredPermissions: ['view_tuition_plan'],
            },
            {
                title: 'Scholarships',
                href: '/scholarships',
                icon: Award,
                requiredPermissions: ['view_scholarship'],
            },
            {
                title: 'Student Scholarships',
                href: '/student-scholarships',
                icon: Users,
                requiredPermissions: ['assign_scholarship'],
            },
            {
                title: 'Vouchers',
                href: '/vouchers',
                icon: Ticket,
                requiredPermissions: ['view_voucher'],
            },
        ],
    },
    {
        title: 'Clubs',
        href: '#',
        icon: Club,
        children: [
            {
                title: 'List',
                href: systemRoutes.clubs.index(),
                icon: Settings2,
                requiredPermissions: ['view_clubs'],
            },
        ],
    },
    {
        title: 'Email',
        href: '#',
        icon: Mail,
        children: [
            {
                title: 'Email Configuration',
                href: systemRoutes.emailConfiguration.index(),
                icon: Settings2,
                requiredPermissions: ['view_email_configuration'],
            },
            {
                title: 'Email Templates',
                href: systemRoutes.emailConfiguration.templates(),
                icon: FileText,
                requiredPermissions: ['view_email_template'],
            },
            {
                title: 'Bulk Email',
                href: systemRoutes.emailConfiguration.bulkEmail(),
                icon: MailPlus,
                requiredPermissions: ['view_email_template'],
            },
            //     Email history
            {
                title: 'Email History',
                href: systemRoutes.emailConfiguration.emailHistory(),
                icon: Clock,
                requiredPermissions: ['view_email_log'],
            },
        ],
    },
    // System
    {
        title: 'System',
        href: '#',
        icon: FileText,
        children: [
            {
                title: 'System Configuration',
                href: systemRoutes.config.index(),
                icon: Settings2,
                requiredPermissions: ['view_system_config'], // Will be implemented later
            },
            {
                title: 'Activity Logs',
                href: systemRoutes.activityLogs.index(),
                icon: Clock,
                requiredPermissions: ['view_system_log'],
            },
            {
                title: 'Email Monitoring',
                href: '/admin/email-monitoring',
                icon: BarChart3,
                requiredPermissions: ['view_email_system'],
            },
        ],
    },
];
