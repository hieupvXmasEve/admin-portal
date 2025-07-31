import type { NavItem } from '@/types';
import {
    ArrowUpRight,
    Award,
    BarChart3,
    BookMarked,
    BookOpen,
    Building2,
    Calendar,
    Calendar as CalendarIcon,
    CheckSquare,
    ClipboardList,
    Clock,
    DoorOpen,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    Layers,
    LayoutDashboard,
    LineChart,
    MapPin,
    PieChart,
    Presentation,
    RefreshCw,
    Repeat,
    School,
    Settings,
    ShieldCheck,
    Star,
    Target,
    TrendingUp,
    Trophy,
    User,
    UserCheck,
    UserPlus,
    Users,
} from 'lucide-vue-next';

import {
    academicSummaryRoutes,
    assessmentRoutes,
    attendanceRoutes,
    courseRoutes,
    curriculumRoutes,
    lecturerRoutes,
    reportRoutes,
    studentRoutes,
    syllabusRoutes,
    systemRoutes,
    transferRoutes,
} from '@/utils/routes';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
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
            {
                title: 'Specializations',
                href: curriculumRoutes.specializations.index(),
                requiredPermissions: ['view_specialization'],
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
            {
                title: 'Program/Specialization Change',
                href: '#', // Placeholder
                icon: RefreshCw,
                requiredPermissions: ['edit_student'], // Will be implemented later
            },
            {
                title: 'Repeat/Retake Courses',
                href: '#', // Placeholder
                icon: Repeat,
                requiredPermissions: ['edit_student'], // Will be implemented later
            },
            {
                title: 'Academic Standing',
                href: '#', // Placeholder
                icon: TrendingUp,
                requiredPermissions: ['view_student'], // Will be implemented later
            },

            {
                title: 'Student Status Tracking',
                href: studentRoutes.statusTracking(),
                icon: UserCheck,
                requiredPermissions: ['view_student'], // Will be implemented later
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
        title: 'Assessments & Grading',
        href: '#',
        icon: Star,
        children: [
            {
                title: 'Assessment Components',
                href: assessmentRoutes.components(),
                icon: Target,
                requiredPermissions: ['view_assessment'], // Will be implemented later
            },
            {
                title: 'Enter Grades',
                href: assessmentRoutes.enterGrades(),
                icon: Star,
                requiredPermissions: ['edit_grade'], // Will be implemented later
            },
            {
                title: 'Academic Results',
                href: assessmentRoutes.academicResults(),
                icon: Trophy,
                requiredPermissions: ['view_grade'], // Will be implemented later
            },
            {
                title: 'Re-assessments',
                href: assessmentRoutes.reAssessments(),
                icon: Repeat,
                requiredPermissions: ['edit_assessment'], // Will be implemented later
            },
            {
                title: 'Semester Grade Reports',
                href: assessmentRoutes.gradeReports(),
                icon: FileSpreadsheet,
                requiredPermissions: ['view_grade'], // Will be implemented later
            },
            {
                title: 'GPA History',
                href: assessmentRoutes.gpaHistory(),
                icon: TrendingUp,
                requiredPermissions: ['view_grade'], // Will be implemented later
            },
        ],
    },
    {
        title: 'Academic Summary',
        href: '#',
        icon: Presentation,
        children: [
            {
                title: 'GPA Calculations',
                href: academicSummaryRoutes.gpaCalculations(),
                icon: Target,
                requiredPermissions: ['view_grade'], // Will be implemented later
            },
            {
                title: 'Degree Classification',
                href: academicSummaryRoutes.degreeClassification(),
                icon: Award,
                requiredPermissions: ['view_grade'], // Will be implemented later
            },
            {
                title: 'Transcript History',
                href: academicSummaryRoutes.transcriptHistory(),
                icon: FileText,
                requiredPermissions: ['view_grade'], // Will be implemented later
            },
            {
                title: 'Academic Performance Report',
                href: academicSummaryRoutes.performanceReport(),
                icon: LineChart,
                requiredPermissions: ['view_grade'], // Will be implemented later
            },
        ],
    },
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
    {
        title: 'Reports & Analytics',
        href: '#',
        icon: BarChart3,
        children: [
            {
                title: 'Student Statistics',
                href: reportRoutes.studentStatistics(),
                icon: Users,
                requiredPermissions: ['view_report'], // Will be implemented later
            },
            {
                title: 'Academic Performance Summary',
                href: reportRoutes.performanceSummary(),
                icon: TrendingUp,
                requiredPermissions: ['view_report'], // Will be implemented later
            },
            {
                title: 'Attendance Summary',
                href: reportRoutes.attendanceSummary(),
                icon: Clock,
                requiredPermissions: ['view_report'], // Will be implemented later
            },
            {
                title: 'GPA Distribution Charts',
                href: reportRoutes.gpaDistribution(),
                icon: PieChart,
                requiredPermissions: ['view_report'], // Will be implemented later
            },
        ],
    },
    {
        title: 'Course Syllabus & Content',
        href: '#',
        icon: FileText,
        children: [
            {
                title: 'Syllabi Management',
                href: '#', // Will use dynamic routes based on unit
                icon: FileText,
                requiredPermissions: ['view_syllabus'],
            },
            {
                title: 'Learning Materials',
                href: syllabusRoutes.learningMaterials(),
                icon: BookOpen,
                requiredPermissions: ['view_syllabus'], // Will be implemented later
            },
            {
                title: 'Course Learning Outcomes',
                href: syllabusRoutes.learningOutcomes(),
                icon: Target,
                requiredPermissions: ['view_syllabus'], // Will be implemented later
            },
            {
                title: 'Assessment Rubrics',
                href: syllabusRoutes.assessmentRubrics(),
                icon: CheckSquare,
                requiredPermissions: ['view_syllabus'], // Will be implemented later
            },
        ],
    },
];
