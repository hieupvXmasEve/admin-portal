import type { NavItem } from '@/types';
import {
    Award,
    BarChart3,
    Bell,
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
    Inbox,
    Layers,
    LayoutDashboard,
    Link2,
    Mail,
    MailPlus,
    Package,
    Receipt,
    School,
    Settings,
    Settings2,
    ShieldCheck,
    Ticket,
    TrendingUp,
    Trophy,
    User,
    UserPlus,
    Users,
    Wallet,
} from 'lucide-vue-next';

import { academicSummaryRoutes, attendanceRoutes, courseRoutes, curriculumRoutes, lecturerRoutes, studentRoutes, systemRoutes } from '@/utils/routes';

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
                title: 'Campuses',
                href: systemRoutes.campuses.index(),
                icon: Building2,
                requiredPermissions: ['view_campus'],
            },
            {
                title: 'Departments',
                href: systemRoutes.departments.index(),
                icon: Layers,
                requiredPermissions: ['manage_departments'],
            },
        ],
    },
    {
        title: 'Room Management',
        href: '#',
        icon: DoorOpen,
        children: [
            {
                title: 'Rooms',
                href: systemRoutes.rooms.index(),
                icon: DoorOpen,
                requiredPermissions: ['view_room'],
            },
            {
                title: 'Bookings',
                href: systemRoutes.roomBookings.index(),
                icon: CalendarIcon,
                requiredPermissions: ['view_room_booking'],
            },
            {
                title: 'My Bookings',
                href: systemRoutes.roomBookings.myBookings(),
                icon: Calendar,
                requiredPermissions: ['create_room_booking'],
            },
            {
                title: 'Pending Approvals',
                href: systemRoutes.roomBookings.pending(),
                icon: Clock,
                requiredPermissions: ['approve_room_booking'],
            },
            {
                title: 'Calendar',
                href: systemRoutes.roomBookings.calendar(),
                icon: CalendarIcon,
                requiredPermissions: ['view_room_booking'],
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
                title: 'Modules',
                href: '/admin/modules',
                icon: Package,
                requiredPermissions: ['view_module'],
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
            {
                title: 'Enrollments & Holds',
                href: studentRoutes.enrollments(), // Placeholder
                icon: ClipboardList,
                requiredPermissions: ['view_student'], // Will be implemented later
            },
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
            {
                title: 'Lecturer Hours',
                href: lecturerRoutes.teachingHours(),
                icon: Clock,
                requiredPermissions: ['view_lecturer'], // Will be implemented later
            },
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
            {
                title: 'Attendance Reports',
                href: attendanceRoutes.attendance.index(),
                icon: FileText,
                requiredPermissions: ['view_attendance'], // Will be implemented later
            },
        ],
    },
    {
        title: 'Reports & Analytics',
        href: '#',
        icon: BarChart3,
        children: [
            {
                title: 'Course Statistics',
                href: '/course-statistics',
                icon: BarChart3,
                requiredPermissions: ['view_attendance'],
            },
            {
                title: 'Failed Students',
                href: '/failed-students',
                icon: ClipboardList,
                requiredPermissions: ['view_attendance'],
            },
            {
                title: 'GPA Management',
                href: academicSummaryRoutes.gpaFinalization(),
                icon: Calculator,
                requiredPermissions: ['view_grade'],
            },
            {
                title: 'GPA History',
                href: academicSummaryRoutes.gpaHistory(),
                icon: Clock,
                requiredPermissions: ['view_grade'],
            },
            {
                title: 'Performance Dashboard',
                href: academicSummaryRoutes.performanceDashboard(),
                icon: BarChart3,
                requiredPermissions: ['view_grade'],
            },
            {
                title: 'Academic Report',
                href: academicSummaryRoutes.academicReport(),
                icon: FileText,
                requiredPermissions: ['view_grade'],
            },
            {
                title: 'Course Ranking',
                href: academicSummaryRoutes.courseRanking(),
                icon: Trophy,
                requiredPermissions: ['view_grade'],
            },
            // Group menu Student Lifecycle
            {
                title: 'Student Status',
                href: '#',
                icon: Users,
                children: [
                    {
                        title: 'Student Actions',
                        href: studentRoutes.studentStatusAction(),
                        icon: ClipboardCheck,
                        requiredPermissions: ['view_student_action'],
                    },
                ],
            },
            // Group menu Progression Audit
            {
                title: 'English certificate audit',
                href: '#',
                icon: Users,
                children: [
                    // Academic Progression Audit
                    {
                        title: 'Academic Progression',
                        href: studentRoutes.academicProgressionAudit(),
                        icon: TrendingUp,
                        requiredPermissions: ['view_student_action'],
                    },
                    // Missing Documents
                    {
                        title: 'Missing Documents',
                        href: studentRoutes.academicProgressionMissingDocuments(),
                        icon: ClipboardCheck,
                        requiredPermissions: ['view_student_action'],
                    },
                ],
            },
            // Student Actions
        ],
    },

    {
        title: 'Forms Engine',
        href: '#',
        icon: FormInput,
        children: [
            {
                title: 'Forms Library',
                href: '/forms/admin',
                icon: BookOpen,
                requiredPermissions: ['view_form'],
            },
            {
                title: 'Runs',
                href: '#',
                icon: Layers,
                children: [
                    {
                        title: 'Runs List',
                        href: '/forms/admin/runs',
                        icon: ClipboardList,
                        requiredPermissions: ['view_form'],
                    },
                    {
                        title: 'Create Run',
                        href: '/forms/admin/runs/create',
                        icon: FormInput,
                        requiredPermissions: ['view_form'],
                    },
                ],
            },
            {
                title: 'Surveys',
                href: '#',
                icon: BarChart3,
                children: [
                    // {
                    //     title: 'Mandatory Gate',
                    //     href: '/forms/admin/gate',
                    //     icon: ShieldCheck,
                    //     requiredPermissions: ['view_form'],
                    // },
                    {
                        title: 'Survey Results',
                        href: '/forms/admin/results',
                        icon: BarChart3,
                        requiredPermissions: ['view_survey_results_aggregate'],
                    },
                ],
            },
            {
                title: 'Queries',
                href: '#',
                icon: ClipboardCheck,
                children: [
                    {
                        title: 'Staff Inbox',
                        href: '/forms/admin/inbox',
                        icon: Inbox,
                        requiredPermissions: ['review_form'],
                    },
                    // {
                    //     title: 'All Queries',
                    //     href: '/forms/queries/list',
                    //     icon: ClipboardCheck,
                    //     requiredPermissions: ['review_form'],
                    // },
                ],
            },
            // {
            //     title: 'Settings',
            //     href: '/forms/queries/settings',
            //     icon: Settings2,
            //     requiredPermissions: ['view_form'],
            // },
        ],
    },

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
        title: 'Canvas LMS Integration',
        href: '#',
        icon: Link2,
        children: [
            {
                title: 'Integrations',
                href: '/admin/canvas/integrations',
                icon: Settings2,
                requiredPermissions: ['view_canvas_integration'],
            },
            {
                title: 'Courses',
                href: '/admin/canvas/courses',
                icon: BookOpen,
                requiredPermissions: ['view_canvas_integration'],
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
                title: 'Student Wallets',
                href: '/wallets',
                icon: Wallet,
                requiredPermissions: ['view_student_wallet'],
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
    {
        title: 'Notification Management',
        href: '#',
        icon: Bell,
        children: [
            {
                title: 'Send Notification',
                href: systemRoutes.notifications.send(),
                icon: MailPlus,
                requiredPermissions: ['send_manual_notification'],
            },
            {
                title: 'History',
                href: systemRoutes.notifications.index(),
                icon: Clock,
                requiredPermissions: ['view_any_notification'],
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
