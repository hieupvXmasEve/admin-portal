<?php
return [
    'access' => [
        'users' => [
            'view_user' => 'view_user',
            'create_user' => 'create_user',
            'edit_user' => 'edit_user',
            'delete_user' => 'delete_user',
            'import_user' => 'import_user',
            'export_user' => 'export_user',
        ],
        'campuses' => [
            'view_campus' => 'view_campus',
            'create_campus' => 'create_campus',
            'edit_campus' => 'edit_campus',
            'delete_campus' => 'delete_campus',
        ],
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
        'units' => [
            'view_unit' => 'view_unit',
            'create_unit' => 'create_unit',
            'edit_unit' => 'edit_unit',
            'delete_unit' => 'delete_unit',
        ],
        'unit-prerequisites' => [
            'view_unit_prerequisite' => 'view_unit_prerequisite',
            'create_unit_prerequisite' => 'create_unit_prerequisite',
            'edit_unit_prerequisite' => 'edit_unit_prerequisite',
            'delete_unit_prerequisite' => 'delete_unit_prerequisite',
        ],
        'equivalent-units' => [
            'view_equivalent_unit' => 'view_equivalent_unit',
            'create_equivalent_unit' => 'create_equivalent_unit',
            'edit_equivalent_unit' => 'edit_equivalent_unit',
            'delete_equivalent_unit' => 'delete_equivalent_unit',
        ],
        'activity' => [
            'view_activity' => 'view_activity',
            'create_activity' => 'create_activity',
            'edit_activity' => 'edit_activity',
            'delete_activity' => 'delete_activity',
        ],
        'room' => [
            'view_room' => 'view_room',
            'create_room' => 'create_room',
            'edit_room' => 'edit_room',
            'delete_room' => 'delete_room',
        ],
        'course' => [
            'view_course' => 'view_course',
            'create_course' => 'create_course',
            'edit_course' => 'edit_course',
            'delete_course' => 'delete_course',
        ],
        'groups' => [
            'view_groups' => 'view_groups',
            'create_groups' => 'create_groups',
            'edit_groups' => 'edit_groups',
            'delete_groups' => 'delete_groups',
            'create_student_group' => 'create_student_group',
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
        'fees' => [
            'view_fees' => 'view_fees',
            'create_fees' => 'create_fees',
            'edit_fees' => 'edit_fees',
            'delete_fees' => 'delete_fees',
            'import_fees' => 'import_fees',
        ],
        'queries' => [
            'view_queries' => 'view_queries',
            'detail_queries' => 'detail_queries',
            'create_queries' => 'create_queries',
            'edit_queries' => 'edit_queries',
            'delete_queries' => 'delete_queries',
        ],
        'golds' => [
            'view_golds' => 'view_golds',
            'create_golds' => 'create_golds',
            'edit_golds' => 'edit_golds',
            'delete_golds' => 'delete_golds',
            'detail_golds' => 'detail_golds',
        ],
        'items' => [
            'view_items' => 'view_items',
            'create_items' => 'create_items',
            'edit_items' => 'edit_items',
            'delete_items' => 'delete_items',
        ],
        'news' => [
            'view_news' => 'view_news',
            'create_news' => 'create_news',
            'edit_news' => 'edit_news',
            'delete_news' => 'delete_news',
        ],
        'semesters' => [
            'view_semester' => 'view_semester',
            'create_semester' => 'create_semester',
            'edit_semester' => 'edit_semester',
            'delete_semester' => 'delete_semester',
        ]
    ],

    'module_parent' => [
        'account',
        'Roles',
        'Permissons',
        'roooms',
        'news',
        'activity',
        'course',
        'terms',
        'groups',
        'events',
        'club',
        'item',
        'fee',
        'queris',
        'gold',
        'semesters',
        'units',
        'unit-prerequisites',
        'equivalent-units'
    ],

    'module_children' => [
        'view',
        'add',
        'edit',
        'delete',
        'import',
        'download'
    ]
];
