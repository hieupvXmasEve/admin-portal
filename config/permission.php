<?php

return [
    'access' => [
        // User Management
        'users' => [
            'view_user' => 'view_user',
            'create_user' => 'create_user',
            'edit_user' => 'edit_user',
            'delete_user' => 'delete_user',
            'import_user' => 'import_user',
            'export_user' => 'export_user',
        ],

        // Campus Management
        'campuses' => [
            'view_campus' => 'view_campus',
            'create_campus' => 'create_campus',
            'edit_campus' => 'edit_campus',
            'delete_campus' => 'delete_campus',
        ],

        // Role & Permission Management
        'roles' => [
            'view_role' => 'view_role',
            'create_role' => 'create_role',
            'edit_role' => 'edit_role',
            'delete_role' => 'delete_role',
        ],
        'permissions' => [
            'view_permission' => 'view_permission',
            'create_permission' => 'create_permission',
            'edit_permission' => 'edit_permission',
            'delete_permission' => 'delete_permission',
        ],

        // Academic Management
        'programs' => [
            'view_program' => 'view_program',
            'create_program' => 'create_program',
            'edit_program' => 'edit_program',
            'delete_program' => 'delete_program',
        ],
        'specializations' => [
            'view_specialization' => 'view_specialization',
            'create_specialization' => 'create_specialization',
            'edit_specialization' => 'edit_specialization',
            'delete_specialization' => 'delete_specialization',
        ],
        'units' => [
            'view_unit' => 'view_unit',
            'create_unit' => 'create_unit',
            'edit_unit' => 'edit_unit',
            'delete_unit' => 'delete_unit',
        ],
        'curriculum_versions' => [
            'view_curriculum_version' => 'view_curriculum_version',
            'create_curriculum_version' => 'create_curriculum_version',
            'edit_curriculum_version' => 'edit_curriculum_version',
            'delete_curriculum_version' => 'delete_curriculum_version',
        ],
        'curriculum_units' => [
            'view_curriculum_unit' => 'view_curriculum_unit',
            'create_curriculum_unit' => 'create_curriculum_unit',
            'edit_curriculum_unit' => 'edit_curriculum_unit',
            'delete_curriculum_unit' => 'delete_curriculum_unit',
        ],

        // Scheduling & Resources
        'semesters' => [
            'view_semester' => 'view_semester',
            'create_semester' => 'create_semester',
            'edit_semester' => 'edit_semester',
            'delete_semester' => 'delete_semester',
        ],
        'rooms' => [
            'view_room' => 'view_room',
            'create_room' => 'create_room',
            'edit_room' => 'edit_room',
            'delete_room' => 'delete_room',
        ],
        'courses' => [
            'view_course' => 'view_course',
            'create_course' => 'create_course',
            'edit_course' => 'edit_course',
            'delete_course' => 'delete_course',
        ],

        // Student Management
        'students' => [
            'view_student' => 'view_student',
            'create_student' => 'create_student',
            'edit_student' => 'edit_student',
            'delete_student' => 'delete_student',
        ],

        // Student Activities
        'groups' => [
            'view_groups' => 'view_groups',
            'create_groups' => 'create_groups',
            'create_student_group' => 'create_student_group',
            'edit_groups' => 'edit_groups',
            'delete_groups' => 'delete_groups',
        ],
        'events' => [
            'view_events' => 'view_events',
            'create_events' => 'create_events',
            'edit_events' => 'edit_events',
            'delete_events' => 'delete_events',
        ],
        'clubs' => [
            'view_clubs' => 'view_clubs',
            'create_clubs' => 'create_clubs',
            'edit_clubs' => 'edit_clubs',
            'delete_clubs' => 'delete_clubs',
        ],

        // Financial Management
        'fees' => [
            'view_fees' => 'view_fees',
            'create_fees' => 'create_fees',
            'import_fees' => 'import_fees',
        ],

        // Reporting & Analytics
        'queries' => [
            'view_queries' => 'view_queries',
            'detail_queries' => 'detail_queries',
        ],
    ],

    // Simplified module structure
    'modules' => [
        'user_management' => ['users', 'roles', 'permissions', 'campuses'],
        'academic_management' => ['programs', 'units', 'curriculum_versions', 'curriculum_units'],
        'scheduling' => ['semesters', 'rooms', 'courses'],
        'student_activities' => ['groups', 'events', 'clubs'],
        'financial' => ['fees'],
        'reporting' => ['queries'],
    ],

    'actions' => [
        'view',
        'create',
        'edit',
        'delete',
        'import',
        'export',
        'detail',
    ],
];
