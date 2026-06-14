import type { NavGroup, NavItem } from '@/types';
import {
    AlertCircle,
    ArrowRightLeft,
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
    FileWarning,
    FormInput,
    GraduationCap,
    Inbox,
    Layers,
    LayoutDashboard,
    Link2,
    Mail,
    MailPlus,
    MessageSquare,
    Package,
    Play,
    Receipt,
    RefreshCcw,
    School,
    Search,
    Send,
    Settings,
    Settings2,
    ShieldCheck,
    Sparkles,
    Ticket,
    TrendingUp,
    Trophy,
    User,
    UserPlus,
    Users,
} from 'lucide-vue-next';

import { academicSummaryRoutes, attendanceRoutes, courseRoutes, curriculumRoutes, lecturerRoutes, studentRoutes, systemRoutes } from '@/utils/routes';

export const mainNavGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: '/dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        label: 'Academic Operations',
        items: [
            {
                title: 'Curriculum Setup',
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
                        icon: GraduationCap,
                        requiredPermissions: ['view_program'],
                    },
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
                ],
            },
            {
                title: 'Course Delivery',
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
                        requiredPermissions: ['view_course_offering'],
                    },
                    {
                        title: 'Course Registration',
                        href: courseRoutes.registrations.index(),
                        icon: UserPlus,
                        requiredPermissions: ['view_course_registration'],
                    },
                    {
                        title: 'Retake Registration',
                        href: '/retake-course',
                        icon: RefreshCcw,
                        requiredPermissions: ['view_retake_course'],
                    },
                    {
                        title: 'Canvas Courses',
                        href: '/admin/canvas/courses',
                        icon: Link2,
                        requiredPermissions: ['view_canvas_integration'],
                    },
                ],
            },
            {
                title: 'Attendance & Completion',
                href: '#',
                icon: ClipboardCheck,
                children: [
                    {
                        title: 'Course Statistics',
                        href: '/course-statistics',
                        icon: BarChart3,
                        requiredPermissions: ['view_attendance'],
                    },
                    {
                        title: 'Attendance Summary',
                        href: attendanceRoutes.attendance.index(),
                        icon: FileText,
                        requiredPermissions: ['view_attendance'],
                    },
                    {
                        title: 'Failed Students',
                        href: '/failed-students',
                        icon: ClipboardList,
                        requiredPermissions: ['view_attendance'],
                    },
                ],
            },
            {
                title: 'Grades & Performance',
                href: '#',
                icon: TrendingUp,
                children: [
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
                        title: 'Warning Center',
                        href: academicSummaryRoutes.warningCenter(),
                        icon: AlertCircle,
                        requiredPermissions: ['view_attendance', 'view_grade'],
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
                ],
            },
        ],
    },
    {
        label: 'Student Services',
        items: [
            {
                title: 'Student Records',
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
                        href: studentRoutes.enrollments(),
                        icon: ClipboardList,
                        requiredPermissions: ['view_student'],
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
                title: 'Status & Decisions',
                href: '#',
                icon: ClipboardList,
                children: [
                    {
                        title: 'Student Actions',
                        href: studentRoutes.studentStatusAction(),
                        icon: ClipboardCheck,
                        requiredPermissions: ['view_student_action'],
                    },
                    {
                        title: 'Lifecycle Yearly Analysis',
                        href: studentRoutes.studentLifecycleYearlyAnalysis(),
                        icon: TrendingUp,
                        requiredPermissions: ['view_student_action'],
                    },
                    {
                        title: 'Student Decisions',
                        href: studentRoutes.studentDecisionsIndex(),
                        icon: FileText,
                        requiredPermissions: ['view_student_action'],
                    },
                ],
            },
            {
                title: 'Progression Audit',
                href: '#',
                icon: AlertCircle,
                children: [
                    {
                        title: 'Academic Progression',
                        href: studentRoutes.academicProgressionAudit(),
                        icon: TrendingUp,
                        requiredPermissions: ['view_student_action'],
                    },
                    {
                        title: 'Missing Documents',
                        href: studentRoutes.academicProgressionMissingDocuments(),
                        icon: ClipboardCheck,
                        requiredPermissions: ['view_student_action'],
                    },
                ],
            },
        ],
    },
    {
        label: 'Faculty & Teaching',
        items: [
            {
                title: 'Lecturer List',
                href: lecturerRoutes.index(),
                icon: Users,
                requiredPermissions: ['view_lecturer'],
            },
            {
                title: 'Lecturer Hours',
                href: lecturerRoutes.teachingHours(),
                icon: Clock,
                requiredPermissions: ['view_lecturer'],
            },
            {
                title: 'Lecturer GPA',
                href: lecturerRoutes.gpa(),
                icon: TrendingUp,
                requiredPermissions: ['view_lecturer', 'view_survey_results_aggregate'],
            },
        ],
    },
    {
        label: 'Finance Office',
        items: [
            {
                title: 'Finance Operations',
                href: '#',
                icon: DollarSign,
                children: [
                    {
                        title: 'Billing Dashboard',
                        href: '/finance/operations/dashboard',
                        icon: LayoutDashboard,
                        requiredPermissions: ['view_finance_operations_dashboard'],
                    },
                    {
                        title: 'Generate HP (Tuition)',
                        href: '/finance/major/charges',
                        icon: Play,
                        requiredPermissions: ['create_finance_charges'],
                    },
                    {
                        title: 'Batch Charges (All Students)',
                        href: '/finance/operations/generate-charges',
                        icon: Layers,
                        requiredPermissions: ['view_finance_operations_generate_charges'],
                    },
                    {
                        title: 'Exceptions Queue',
                        href: '/finance/operations/exceptions',
                        icon: AlertCircle,
                        requiredPermissions: ['view_finance_operations_exceptions'],
                    },
                    {
                        title: 'Settlement Worklist',
                        href: '/finance/operations/settlement',
                        icon: Sparkles,
                        requiredPermissions: ['allocate_finance_payment'],
                    },
                    {
                        title: 'DNG Due Reminders',
                        href: '/finance/operations/due-calendar',
                        icon: CalendarIcon,
                        requiredPermissions: ['view_finance_operations_due_calendar'],
                    },
                    {
                        title: 'Lifecycle Exceptions',
                        href: '/finance/operations/lifecycle-exceptions',
                        icon: FileWarning,
                        requiredPermissions: ['view_finance_operations_due_calendar'],
                    },
                    {
                        title: 'Lifecycle History',
                        href: '/finance/operations/lifecycle-exception-history',
                        icon: Clock,
                        requiredPermissions: ['view_finance_operations_due_calendar'],
                    },
                ],
            },
            {
                title: 'EGC Finance',
                href: '#',
                icon: GraduationCap,
                requiredPermissions: ['view_egc_finance_operations'],
                children: [
                    {
                        title: 'Generate Charges',
                        href: '/finance/egc/charges',
                        icon: Play,
                        requiredPermissions: ['generate_egc_finance_charges'],
                    },
                    {
                        title: 'Block Results',
                        href: '/finance/egc/block-results',
                        icon: CheckSquare,
                        requiredPermissions: ['view_egc_block_results'],
                    },
                    {
                        title: 'Retake Adjustments',
                        href: '/finance/egc/retake-adjustments',
                        icon: TrendingUp,
                        requiredPermissions: ['view_egc_retake_adjustments'],
                    },
                    {
                        title: 'Carry Forward',
                        href: '/finance/egc/carry-forward',
                        icon: ArrowRightLeft,
                        requiredPermissions: ['view_egc_retake_adjustments'],
                    },
                    {
                        title: 'DNG Due Reminders',
                        href: '/finance/operations/due-calendar',
                        icon: CalendarIcon,
                        requiredPermissions: ['view_finance_operations_due_calendar'],
                    },
                ],
            },
            {
                title: 'Ledgers & Payments',
                href: '#',
                icon: Receipt,
                children: [
                    {
                        title: 'Charge Ledger (Global)',
                        href: '/finance/charges',
                        icon: BarChart3,
                        requiredPermissions: ['view_finance_charges'],
                    },
                    {
                        title: 'Invoices',
                        href: '/finance/invoices',
                        icon: Receipt,
                        requiredPermissions: ['view_finance_invoices'],
                    },
                    {
                        title: 'Payments',
                        href: '/finance/payments',
                        icon: BarChart3,
                        requiredPermissions: ['view_finance_payments'],
                    },
                    {
                        title: 'DNG Worklist',
                        href: '/finance/operations/dng-worklist',
                        icon: Send,
                        requiredPermissions: ['create_finance_payments'],
                    },
                    {
                        title: 'DNG Payment Requests',
                        href: '/finance/dng/payment-requests',
                        icon: Receipt,
                        requiredPermissions: ['view_finance_dng_payment_requests'],
                    },
                    {
                        title: 'DNG Webhook Events',
                        href: '/finance/dng/webhook-events',
                        icon: Receipt,
                        requiredPermissions: ['view_finance_dng_webhook_events'],
                    },
                ],
            },
            {
                title: 'Discounts & Funding',
                href: '#',
                icon: Award,
                children: [
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
        ],
    },
    {
        label: 'Forms & Quality',
        items: [
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
                    {
                        title: 'Survey Results',
                        href: '/forms/admin/results',
                        icon: BarChart3,
                        requiredPermissions: ['view_survey_results_aggregate'],
                    },
                    {
                        title: 'Program Stats',
                        href: '/forms/admin/results/stats',
                        icon: TrendingUp,
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
                ],
            },
        ],
    },
    {
        label: 'Campus Operations',
        items: [
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
                        title: 'Find Available Rooms',
                        href: systemRoutes.roomBookings.availability(),
                        icon: Search,
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
                        title: 'Room Usage Calendar',
                        href: systemRoutes.roomBookings.calendar(),
                        icon: CalendarIcon,
                        requiredPermissions: ['view_room_booking'],
                    },
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
                        title: 'Event Reports',
                        href: systemRoutes.events.reports(),
                        icon: BarChart3,
                        requiredPermissions: ['view_event'],
                    },
                ],
            },
            {
                title: 'Clubs',
                href: systemRoutes.clubs.index(),
                icon: Club,
                requiredPermissions: ['view_clubs'],
            },
        ],
    },
    {
        label: 'Communications',
        items: [
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
                        icon: Send,
                        requiredPermissions: ['send_manual_notification'],
                    },
                    {
                        title: 'Email Templates',
                        href: '/admin/notification-templates',
                        icon: FileText,
                        requiredPermissions: ['view_notification_email_template'],
                    },
                    {
                        title: 'Ops',
                        href: '#',
                        icon: Settings2,
                        children: [
                            {
                                title: 'Outbox',
                                href: systemRoutes.notifications.ops.outbox(),
                                icon: Inbox,
                                requiredPermissions: ['view_notification_ops'],
                            },
                            {
                                title: 'Messages',
                                href: systemRoutes.notifications.ops.messages(),
                                icon: MessageSquare,
                                requiredPermissions: ['view_notification_ops'],
                            },
                            {
                                title: 'Deliveries',
                                href: systemRoutes.notifications.ops.deliveries(),
                                icon: Send,
                                requiredPermissions: ['view_notification_ops'],
                            },
                        ],
                    },
                ],
            },
        ],
    },
    {
        label: 'Administration',
        items: [
            {
                title: 'Identity & Access',
                href: '#',
                icon: ShieldCheck,
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
                ],
            },
            {
                title: 'Organization',
                href: '#',
                icon: Building2,
                children: [
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
                title: 'Integrations',
                href: '#',
                icon: Link2,
                children: [
                    {
                        title: 'Canvas Integrations',
                        href: '/admin/canvas/integrations',
                        icon: Settings2,
                        requiredPermissions: ['view_canvas_integration'],
                    },
                ],
            },
            {
                title: 'System Operations',
                href: '#',
                icon: Settings,
                children: [
                    {
                        title: 'System Configuration',
                        href: systemRoutes.config.index(),
                        icon: Settings2,
                        requiredPermissions: ['view_system_config'],
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
        ],
    },
];

export const mainNavItems: NavItem[] = mainNavGroups.flatMap((group) => group.items);
