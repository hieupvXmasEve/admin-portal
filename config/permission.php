<?php
return [
    'access' => [
        'users' => [
            'view_user' => 'view_user',
            'add_user' => 'add_user',
            'edit_user' => 'edit_user',
            'delete_user' => 'delete_user',
        ],
        'roles' => [
            'view_roles' => 'view_roles',
            'add_roles' => 'add_roles',
            'edit_roles' => 'edit_roles',
            'delete_roles' => 'delete_roles',
        ],
        'permissions' => [
            'view_permissions' => 'view_permissions',
            'add_permissions' => 'add_permissions',
            'edit_permissions' => 'edit_permissions',
            'delete_permissions' => 'delete_permissions',
        ],
        'activity' => [
            'view_activity' => 'view_activity',
            'add_activity' => 'add_activity',
            'edit_activity' => 'edit_activity',
            'delete_activity' => 'delete_activity',
        ],
        'room' => [
            'view_room' => 'view_room',
            'add_room' => 'add_room',
            'edit_room' => 'edit_room',
            'delete_room' => 'delete_room',
        ],
        'course' => [
            'view_course' => 'view_course',
            'add_course' => 'add_course',
            'edit_course' => 'edit_course',
            'delete_course' => 'delete_course',
        ],
        'terms' => [
            'view_terms' => 'view_terms',
            'add_terms' => 'add_terms',
            'edit_terms' => 'edit_terms',
            'delete_terms' => 'delete_terms',
        ],
        'groups' => [
            'view_groups' => 'view_groups',
            'add_groups' => 'add_groups',
            'edit_groups' => 'edit_groups',
            'delete_groups' => 'delete_groups',
        ],
        'events' => [
            'view_events' => 'view_events',
            'add_events' => 'add_events',
            'edit_events' => 'edit_events',
            'delete_events' => 'delete_events',
        ],
        'clubs' => [
            'view_clubs' => 'view_clubs',
            'add_clubs' => 'add_clubs',
            'edit_clubs' => 'edit_clubs',
            'delete_clubs' => 'delete_clubs',
        ],
        'fees' => [
            'view_fees' => 'view_fees',
            'add_fees' => 'add_fees',
            'edit_fees' => 'edit_fees',
            'delete_fees' => 'delete_fees',
        ],
        'queries' => [
            'view_queries' => 'view_queries',
            'add_queries' => 'add_queries',
            'edit_queries' => 'edit_queries',
            'delete_queries' => 'delete_queries',
        ],
        'golds' => [
            'view_golds' => 'view_golds',
            'add_golds' => 'add_golds',
            'edit_golds' => 'edit_golds',
            'delete_golds' => 'delete_golds',
        ],
        'items' => [
            'view_items' => 'view_items',
            'add_items' => 'add_items',
            'edit_items' => 'edit_items',
            'delete_items' => 'delete_items',
        ],
        'news' => [
            'view_news' => 'view_news',
            'add_news' => 'add_news',
            'edit_news' => 'edit_news',
            'delete_news' => 'delete_news',
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
        'gold'
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
