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
    MessageSquare,
    Package,
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
    UserPlus,
    Users,
} from 'lucide-vue-next';

import { academicSummaryRoutes, attendanceRoutes, courseRoutes, curriculumRoutes, financeRoutes, lecturerRoutes, studentRoutes, systemRoutes } from '@/utils/routes';

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
                        title: 'Thi lại',
                        href: '/exam-resit',
                        icon: GraduationCap,
                        requiredPermissions: ['view_exam_resit'],
                    },
                    {
                        title: 'Lịch thi lại',
                        href: '/exam-schedule',
                        icon: CalendarIcon,
                        requiredPermissions: ['manage_exam_schedule'],
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
                        title: 'Warning Center',
                        href: academicSummaryRoutes.warningCenter(),
                        icon: AlertCircle,
                        requiredPermissions: ['view_attendance', 'view_grade'],
                    },
                    // Aggregate analytics (Performance Dashboard, Academic Report, Course Ranking)
                    // live in the management "Reports & Audits" group (ADR-0007, issue 07).
                ],
            },
        ],
    },
    {
        // Per-student work (ADR-0007): reached through the single "Student" entry,
        // which opens the student list and from there the per-student Student Hub.
        // The aggregate audits/reports that used to be scattered here now live in the
        // separate management "Reports & Audits" group below (issue 07).
        label: 'Student Services',
        items: [
            {
                title: 'Students',
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
            {
                title: 'CRM Value Mappings',
                href: '/student-applications/crm-mappings',
                icon: Link2,
                requiredPermissions: ['manage_crm_value_mapping'],
            },
        ],
    },
    {
        // Management oversight area for Directors/Heads (ADR-0007): the aggregate
        // audits/reports, kept separate from the per-student Hub. Each report row
        // deep-links one-way into the relevant student's Hub tab; there is no
        // Hub → report back-link (issue 07).
        label: 'Reports & Audits',
        items: [
            {
                title: 'Lifecycle & Decisions',
                href: '#',
                icon: ClipboardList,
                children: [
                    {
                        title: 'Student Actions Audit',
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
                    {
                        title: 'Missing Decisions',
                        href: studentRoutes.academicProgressionMissingDecisions(),
                        icon: FileWarning,
                        requiredPermissions: ['view_student_action'],
                    },
                    {
                        title: 'Defer Return Watchlist',
                        href: studentRoutes.academicProgressionDeferReturns(),
                        icon: Clock,
                        requiredPermissions: ['view_student_action'],
                    },
                    {
                        title: 'Scholarship Restoration Watchlist',
                        href: studentRoutes.scholarshipRestorationWatchlist(),
                        icon: Clock,
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
                title: 'Academic Performance',
                href: '#',
                icon: BarChart3,
                children: [
                    {
                        title: 'Performance Dashboard',
                        href: academicSummaryRoutes.performanceDashboard(),
                        icon: BarChart3,
                        requiredPermissions: ['view_grade'],
                    },
                    {
                        title: 'Course Ranking',
                        href: academicSummaryRoutes.courseRanking(),
                        icon: Trophy,
                        requiredPermissions: ['view_grade'],
                    },
                    {
                        title: 'Academic Report',
                        href: academicSummaryRoutes.academicReport(),
                        icon: FileText,
                        requiredPermissions: ['view_grade'],
                    },
                    {
                        title: 'Student Completed Units',
                        href: academicSummaryRoutes.studentCompletedUnits(),
                        icon: FileText,
                        requiredPermissions: ['view_academic_report'],
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
        label: 'Finance Office (New UI)',
        items: [
            {
                title: 'Hôm nay',
                href: financeRoutes.cockpit.index(),
                icon: LayoutDashboard,
                requiredPermissions: ['view_finance_cockpit'],
            },
            {
                title: 'Finance Reporting',
                href: financeRoutes.reporting.index(),
                icon: BarChart3,
                requiredPermissions: ['view_finance_reporting'],
            },
            {
                title: 'Doanh thu',
                href: financeRoutes.revenue.index(),
                icon: TrendingUp,
                requiredPermissions: ['view_finance_revenue_report'],
            },
            {
                title: 'Sinh phí',
                href: '#',
                icon: DollarSign,
                children: [
                    { title: 'Batch Studio', href: financeRoutes.batchStudio.hub(), icon: Layers, requiredPermissions: ['view_finance_batch_studio'] },
                    {
                        title: 'Pricing Operations',
                        href: financeRoutes.pricingOperations.index(),
                        icon: DollarSign,
                        requiredPermissions: ['view_finance_pricing_operations'],
                    },
                    {
                        title: 'EGC · Kết quả & học lại',
                        href: financeRoutes.feeGeneration.egcBlockResults(),
                        icon: TrendingUp,
                        requiredPermissions: ['view_egc_block_results'],
                    },
                    { title: 'EGC - Carry Forward', href: financeRoutes.feeGeneration.egcCarryForward(), icon: ArrowRightLeft, requiredPermissions: ['view_egc_retake_adjustments'] },
                ],
            },
            {
                title: 'Thu & Đối soát',
                href: '#',
                icon: Send,
                children: [
                    { title: 'DNG Due Reminders', href: financeRoutes.collect.dueReminders(), icon: CalendarIcon, requiredPermissions: ['view_finance_operations_due_calendar'] },
                    { title: 'DNG Campus Mapping', href: financeRoutes.collect.dngCampusMapping(), icon: Settings2, requiredPermissions: ['view_finance_dng_campus_mappings'] },
                    { title: 'Lập yêu cầu thanh toán DNG', href: financeRoutes.batchStudio.dng(), icon: Send, requiredPermissions: ['view_finance_batch_studio', 'create_finance_payments'] },
                    { title: 'Settlement Worklist', href: financeRoutes.collect.settlement(), icon: Sparkles, requiredPermissions: ['allocate_finance_payment'] },
                    { title: 'Payments', href: financeRoutes.collect.payments(), icon: BarChart3, requiredPermissions: ['view_finance_payments'] },
                    { title: 'Finance Settings', href: financeRoutes.settings.show(), icon: Settings2, requiredPermissions: ['view_finance_settings'] },
                ],
            },
            {
                title: 'Ngoại lệ',
                href: '#',
                icon: FileWarning,
                children: [
                    { title: 'Exceptions Queue', href: financeRoutes.exceptions.queue(), icon: AlertCircle, requiredPermissions: ['view_finance_operations_exceptions'] },
                    { title: 'Lifecycle Exceptions', href: financeRoutes.exceptions.lifecycle(), icon: FileWarning, requiredPermissions: ['view_finance_operations_due_calendar'] },
                    { title: 'Lifecycle History', href: financeRoutes.exceptions.lifecycleHistory(), icon: Clock, requiredPermissions: ['view_finance_operations_due_calendar'] },
                ],
            },
            {
                title: 'Tra cứu & Audit',
                href: '#',
                icon: Search,
                children: [
                    { title: 'Audit Workspace', href: financeRoutes.audit(), icon: Search, requiredPermissions: ['view_finance_audit_workspace'] },
                    { title: 'Charge Ledger (Global)', href: financeRoutes.lookup.chargeLedger(), icon: BarChart3, requiredPermissions: ['view_finance_charges'] },
                    { title: 'Invoices', href: financeRoutes.lookup.invoices(), icon: Receipt, requiredPermissions: ['view_finance_invoices'] },
                    { title: 'DNG Payment Requests', href: financeRoutes.collect.dngPaymentRequests(), icon: Receipt, requiredPermissions: ['view_finance_dng_payment_requests'] },
                    { title: 'DNG · Cần kiểm tra', href: financeRoutes.collect.dngReceiptExceptions(), icon: AlertCircle, requiredPermissions: ['view_finance_dng_receipt_exceptions'] },
                    { title: 'DNG Webhook Events', href: financeRoutes.collect.dngWebhookEvents(), icon: Receipt, requiredPermissions: ['view_finance_dng_receipt_exceptions'] },
                ],
            },
        ],
    },
    // {
    //     label: 'Finance Legacy (Old UI)',
    //     items: [
    //         {
    //             title: 'Legacy Sinh phí',
    //             href: '#',
    //             icon: DollarSign,
    //             children: [
    //                 { title: 'Sinh HP/Tuition', href: financeRoutes.feeGeneration.majorCharges(), icon: Play, requiredPermissions: ['create_finance_charges'] },
    //                 { title: 'Batch Studio Sinh phí', href: financeRoutes.feeGeneration.batchCharges(), icon: Layers, requiredPermissions: ['view_finance_batch_studio'] },
    //                 { title: 'Sinh phí EGC', href: financeRoutes.feeGeneration.egcCharges(), icon: GraduationCap, requiredPermissions: ['generate_egc_finance_charges'] },
    //                 { title: 'EGC - Block Results', href: financeRoutes.feeGeneration.egcBlockResults(), icon: CheckSquare, requiredPermissions: ['view_egc_block_results'] },
    //                 { title: 'EGC - Retake Adjustments', href: financeRoutes.feeGeneration.egcRetakeAdjustments(), icon: TrendingUp, requiredPermissions: ['view_egc_retake_adjustments'] },

    //             ],
    //         },
    //         {
    //             title: 'Legacy Thu & Đối soát',
    //             href: '#',
    //             icon: Send,
    //             children: [],
    //         },
    //         {
    //             title: 'Legacy Reports',
    //             href: '#',
    //             icon: LayoutDashboard,
    //             children: [{ title: 'Billing KPIs (Legacy)', href: financeRoutes.today.dashboard(), icon: LayoutDashboard, requiredPermissions: ['view_finance_operations_dashboard'] }],
    //         },
    //     ],
    // },
    {
        label: 'Discounts & Funding',
        items: [
            {
                title: 'Discounts & Funding',
                href: '#',
                icon: Award,
                children: [
                    { title: 'Tuition Plans', href: '/tuition-plans', icon: Calculator, requiredPermissions: ['view_tuition_plan'] },
                    { title: 'Scholarships', href: '/scholarships', icon: Award, requiredPermissions: ['view_scholarship'] },
                    { title: 'Student Scholarships', href: '/student-scholarships', icon: Users, requiredPermissions: ['view_scholarship'] },
                    { title: 'Scholarship Adjustments', href: '/scholarship-adjustments', icon: Award, requiredPermissions: ['view_scholarship_adjustment'] },
                    { title: 'Vouchers', href: '/vouchers', icon: Ticket, requiredPermissions: ['view_voucher'] },
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
            {
                title: 'Merchandise',
                href: '#',
                icon: Package,
                children: [
                    {
                        title: 'Store',
                        href: systemRoutes.merchandise.index(),
                        icon: Package,
                        requiredPermissions: ['view_merchandise'],
                    },
                    {
                        title: 'Redemption Orders',
                        href: systemRoutes.redemptionOrders.index(),
                        icon: ClipboardList,
                        requiredPermissions: ['view_redemption_order'],
                    },
                    {
                        title: 'Reports',
                        href: systemRoutes.merchandise.reports(),
                        icon: BarChart3,
                        requiredPermissions: ['view_merchandise_report'],
                    },
                ],
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
                    {
                        title: 'Staff Copilot',
                        href: systemRoutes.aiCopilot.index(),
                        icon: MessageSquare,
                        requiredPermissions: ['view_ai_metrics'],
                    },
                    {
                        title: 'AI Provider Settings',
                        href: systemRoutes.aiProviderSettings.index(),
                        icon: Sparkles,
                        requiredPermissions: ['view_ai_provider_settings'],
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
                ],
            },
        ],
    },
];

export const mainNavItems: NavItem[] = mainNavGroups.flatMap((group) => group.items);
