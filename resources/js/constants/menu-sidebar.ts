import type { NavItem } from '@/types';
import {
    BookOpen,
    Calendar,
    GraduationCap,
    LayoutDashboard,
    Shield,
    ShieldCheck,
    Users,
    Building2,
    BookMarked,
    Layers,
    User,
} from 'lucide-vue-next';

export const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Academic',
        href: '#',
        icon: BookOpen,
        children: [

            {
                title: 'Units',
                href: '/units',
                icon: GraduationCap,
                requiredPermissions: ['view_unit'],
            },
            {
                title: 'Programs',
                href: '/programs',
                icon: Building2,
                requiredPermissions: ['view_program'],
            },
            {
                title: 'Semesters',
                href: '/semesters',
                icon: Calendar,
                requiredPermissions: ['view_semester'],
            },
            {
                title: 'Curriculum Versions',
                href: '/curriculum-versions',
                icon: Layers,
                requiredPermissions: ['view_curriculum_version'],
            },
            {
                title: 'Course Offerings',
                href: '/course-offerings',
                icon: BookOpen,
                requiredPermissions: ['view_course_offering'],
            },
            {
                title: 'Course Registrations',
                href: '/course-registrations',
                icon: BookMarked,
                requiredPermissions: ['view_course_registration'],
            },
        ],
    },
    {
        title: 'User Management',
        href: '#',
        icon: Users,
        children: [
            {
                title: 'All Users',
                href: '/users',
                icon: Users,
                requiredPermissions: ['view_user'],
            },
            // {
            //     title: 'Add User',
            //     href: '/users/create',
            //     icon: UserPlus,
            //     requiredPermissions: ['add_user'],
            // },
            // {
            //     title: 'User Roles',
            //     href: '/users/roles',
            //     icon: UserCheck,
            //     requiredPermissions: ['view_user'],
            // },
            {
                title: 'Students',
                href: '/students',
                icon: User,
                requiredPermissions: ['view_student'],
            },
        ],
    },
    {
        title: 'Access Control',
        href: '#',
        icon: Shield,
        children: [
            {
                title: 'Roles',
                href: '#',
                icon: ShieldCheck,
                children: [
                    {
                        title: 'All Roles',
                        href: '/roles',
                    },
                    // {
                    //     title: 'Create Role',
                    //     href: '/roles/create',
                    // },
                    // {
                    //     title: 'Role Permissions',
                    //     href: '/roles/permissions',
                    // },
                ],
            },
            // {
            //     title: 'Permissions',
            //     href: '#',
            //     icon: Shield,
            //     children: [
            //         {
            //             title: 'All Permissions',
            //             href: '/permissions',
            //         },
            //         {
            //             title: 'Create Permission',
            //             href: '/permissions/create',
            //         },
            //     ],
            // },
        ],
    },
    // {
    //     title: 'Reports',
    //     href: '#',
    //     icon: BarChart3,
    //     children: [
    //         {
    //             title: 'Analytics',
    //             href: '#',
    //             icon: BarChart3,
    //             children: [
    //                 {
    //                     title: 'User Analytics',
    //                     href: '/reports/analytics/users',
    //                 },
    //                 {
    //                     title: 'System Analytics',
    //                     href: '/reports/analytics/system',
    //                 },
    //                 {
    //                     title: 'Performance Metrics',
    //                     href: '/reports/analytics/performance',
    //                 },
    //             ],
    //         },
    //         {
    //             title: 'User Activity',
    //             href: '/reports/activity',
    //             icon: FileText,
    //         },
    //         {
    //             title: 'System Logs',
    //             href: '#',
    //             icon: Database,
    //             children: [
    //                 {
    //                     title: 'Error Logs',
    //                     href: '/reports/logs/errors',
    //                 },
    //                 {
    //                     title: 'Access Logs',
    //                     href: '/reports/logs/access',
    //                 },
    //                 {
    //                     title: 'Audit Logs',
    //                     href: '/reports/logs/audit',
    //                 },
    //             ],
    //         },
    //     ],
    // },
    // {
    //     title: 'Communication',
    //     href: '#',
    //     icon: Mail,
    //     children: [
    //         {
    //             title: 'Messages',
    //             href: '/messages',
    //             icon: Mail,
    //         },
    //         {
    //             title: 'Notifications',
    //             href: '#',
    //             icon: Calendar,
    //             children: [
    //                 {
    //                     title: 'All Notifications',
    //                     href: '/notifications',
    //                 },
    //                 {
    //                     title: 'Email Templates',
    //                     href: '/notifications/templates',
    //                 },
    //                 {
    //                     title: 'Push Settings',
    //                     href: '/notifications/push-settings',
    //                 },
    //             ],
    //         },
    //     ],
    // },
    // {
    //     title: 'System',
    //     href: '#',
    //     icon: Settings,
    //     children: [
    //         {
    //             title: 'Settings',
    //             href: '#',
    //             icon: Settings,
    //             children: [
    //                 {
    //                     title: 'General Settings',
    //                     href: '/settings/general',
    //                 },
    //                 {
    //                     title: 'Security Settings',
    //                     href: '/settings/security',
    //                 },
    //                 {
    //                     title: 'Email Settings',
    //                     href: '/settings/email',
    //                 },
    //             ],
    //         },
    //         {
    //             title: 'Maintenance',
    //             href: '/maintenance',
    //             icon: Database,
    //         },
    //     ],
    // },
];
