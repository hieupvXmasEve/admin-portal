<?php

declare(strict_types=1);

return [
    /*
    | Models remain physically shared during staged extraction. This map records
    | the module that owns each legacy table so its own references are not
    | misclassified as cross-context shared-model debt.
    */
    'owned_shared_models' => [
        'Academic' => [
            'Catalog' => [
                'Program',
                'Semester',
                'Unit',
                'EquivalentUnit',
                'UnitPrerequisiteCondition',
                'UnitPrerequisiteGroup',
            ],
            'Delivery' => [
                'CourseOffering',
                'CourseRegistration',
            ],
            'Progression' => [
                'AcademicRecord',
            ],
        ],
        'Admissions' => [
            'ApplicationDocument',
            'ApplicationDocumentType',
            'ApplicationGuardian',
            'StudentApplication',
        ],
    ],

    /*
    | The scanner deliberately reads source roots only. Runtime output, package
    | dependencies, and the separately versioned portal repositories are not
    | application debt in this inventory.
    */
    'source_roots' => [
        'app',
        'routes',
        'resources/js',
        'config/mcp.php',
    ],

    'excluded_path_fragments' => [
        '/vendor/',
        '/node_modules/',
        '/storage/',
        '/bootstrap/cache/',
        '/public/build/',
        '/FE/',
        '/repomix-output.xml',
    ],

    'runtime_surfaces' => [
        'http',
        'api',
        'queue',
        'scheduler',
        'commands',
        'imports_exports',
        'reports',
        'webhooks',
        'broadcasts',
        'mcp',
        'portal',
        'frontend',
        'other',
    ],

    'required_runtime_surfaces' => [
        'http',
        'api',
        'queue',
        'scheduler',
        'commands',
        'imports_exports',
        'reports',
        'webhooks',
        'broadcasts',
        'mcp',
        'portal',
    ],

    /*
    | These are the approved transitional categories. Every entry is required
    | to explain why it exists, where it is going, and what proves retirement.
    | A `max` baseline may only decrease. An `exact` baseline is a zero-tolerance
    | rule and is used for patterns that must never be introduced again.
    */
    'allowlists' => [
        'frozen_services' => [
            'owner' => 'Owning domain teams with Platform Team oversight',
            'reason' => 'Existing service-led runtime paths remain during vertical cutover.',
            'canonical_replacement' => 'Owning module Actions, Queries, or permanent domain support.',
            'retirement_condition' => 'All supported callers are cut over and behavior evidence passes.',
            'baseline' => 119,
            'mode' => 'max',
        ],
        'frozen_controllers' => [
            'owner' => 'Owning domain team',
            'reason' => 'Existing top-level controllers preserve public web/API contracts during migration.',
            'canonical_replacement' => 'Thin controller under the owning module HTTP boundary.',
            'retirement_condition' => 'Route ownership and all supported callers move to the module controller.',
            'baseline' => 120,
            'mode' => 'max',
        ],
        'frozen_routes' => [
            'owner' => 'Platform Team and owning domain team',
            'reason' => 'Existing split route files still serve supported URLs and route names.',
            'canonical_replacement' => 'Owning module routes with a compatibility-preserving mount.',
            'retirement_condition' => 'No supported caller depends on the legacy file and route evidence is green.',
            'baseline' => 42,
            'mode' => 'max',
        ],
        'shared_model_imports' => [
            'owner' => 'Owning module of each shared model',
            'reason' => 'Module consumers still use transitional shared Eloquent models.',
            'canonical_replacement' => 'Shared Contract, neutral reference, Domain Event, or Read Projection.',
            'retirement_condition' => 'The consumer is cut over and the shared-model read is removed.',
            'baseline' => 568,
            'mode' => 'max',
        ],
        'cross_context_concrete_imports' => [
            'owner' => 'Platform architecture maintainers',
            'reason' => 'This is a zero-tolerance boundary rule; existing modules must stay independent.',
            'canonical_replacement' => 'Shared Contract, Domain Event, neutral reference, or declared projection.',
            'retirement_condition' => 'Immediate rejection of every new concrete cross-context import.',
            'baseline' => 0,
            'mode' => 'exact',
        ],
        'direct_json_responses' => [
            'owner' => 'Owning API context',
            'reason' => 'Legacy JSON response sites remain while API envelopes are migrated.',
            'canonical_replacement' => 'ApiResponse::success(), error(), paginated(), or validationError().',
            'retirement_condition' => 'The endpoint uses the canonical envelope and its contract test passes.',
            'baseline' => 56,
            'mode' => 'max',
        ],
        'inline_request_validation' => [
            'owner' => 'Owning HTTP context',
            'reason' => 'Legacy controllers still validate inline during staged extraction.',
            'canonical_replacement' => 'A typed FormRequest owned by the endpoint context.',
            'retirement_condition' => 'The controller delegates validation to the FormRequest.',
            'baseline' => 96,
            'mode' => 'max',
        ],
        'missing_strict_types' => [
            'owner' => 'Platform Team',
            'reason' => 'Older application and route PHP files predate the strict-types standard.',
            'canonical_replacement' => 'declare(strict_types=1) at the top of every application PHP file.',
            'retirement_condition' => 'The file is migrated or receives strict types with behavior unchanged.',
            'baseline' => 257,
            'mode' => 'max',
        ],
        'missing_route_strict_types' => [
            'owner' => 'Platform Team and owning route context',
            'reason' => 'Older split route files predate the strict-types standard.',
            'canonical_replacement' => 'declare(strict_types=1) at the top of every application route PHP file.',
            'retirement_condition' => 'The route file is migrated or receives strict types with route behavior unchanged.',
            'baseline' => 30,
            'mode' => 'max',
        ],
        'legacy_filter_stacks' => [
            'owner' => 'Frontend Team and owning product team',
            'reason' => 'Existing pages still use transitional filter composables.',
            'canonical_replacement' => 'useDataTable with the shared server-table workflow.',
            'retirement_condition' => 'The page preserves query/pagination behavior on useDataTable.',
            'baseline' => 28,
            'mode' => 'max',
        ],
        'literal_frontend_urls' => [
            'owner' => 'Frontend Team and owning product team',
            'reason' => 'Existing router/fetch navigation calls contain literal application paths; this scanner covers all resources/js files, so its baseline is intentionally scanner-specific.',
            'canonical_replacement' => 'Ziggy route() or an established typed route helper.',
            'retirement_condition' => 'The touched flow uses a helper and its route contract remains green.',
            'baseline' => 61,
            'mode' => 'max',
        ],
        'legacy_page_directories' => [
            'owner' => 'Frontend Team and owning product team',
            'reason' => 'Existing Inertia page directories use lowercase or kebab-case names.',
            'canonical_replacement' => 'PascalCase page directories with matching resolver paths.',
            'retirement_condition' => 'The directory is moved with cross-platform render/import verification.',
            'baseline' => 23,
            'mode' => 'max',
        ],
        'removed_inertia_apis' => [
            'owner' => 'Frontend and Platform Teams',
            'reason' => 'Inertia v2 APIs are removed in the Laravel 13/Inertia v3 baseline.',
            'canonical_replacement' => 'Inertia v3 defer/optional/flash and the v3 Deferred prop contract.',
            'retirement_condition' => 'Immediate rejection; no supported code may introduce a removed API.',
            'baseline' => 0,
            'mode' => 'exact',
        ],
        'migration_commands' => [
            'owner' => 'Owning data/domain team',
            'reason' => 'Read-only or approved historical migration commands remain discoverable during closure.',
            'canonical_replacement' => 'A normal runtime path, an approved idempotent migration, or a retired command.',
            'retirement_condition' => 'The command has an owner, approval evidence, and no remaining supported need.',
            'baseline' => 7,
            'mode' => 'max',
        ],
    ],

    'allowlist_entry_baseline' => 14,

    'portal_contracts' => [
        'student' => [
            'owner' => 'Student Registry / Student Portal',
            'reason' => 'Student API routes and documentation define an independently versioned portal contract.',
            'canonical_replacement' => 'Versioned Student API plus matching FE/student-nuxt types and composables.',
            'retirement_condition' => 'Contract changes are released with student portal validation and evidence.',
            'paths' => [
                'routes/api/v1/student.php',
                'app/Modules/Identity/routes/api.php',
                'docs/api/student',
            ],
            'consumer_paths' => [
                'FE/student-nuxt/shared/types',
                'FE/student-nuxt/app/composables',
                'FE/student-nuxt/app/stores',
                'FE/student-nuxt/app/pages',
            ],
        ],
        'lecturer' => [
            'owner' => 'Identity/Academic / Lecturer Portal',
            'reason' => 'Lecturer API routes and documentation define an independently versioned portal contract.',
            'canonical_replacement' => 'Versioned Lecturer API plus matching FE/lecturer-nuxt types and composables.',
            'retirement_condition' => 'Contract changes are released with lecturer portal validation and evidence.',
            'paths' => [
                'routes/api/v1/lecturer.php',
                'app/Modules/Identity/routes/api.php',
                'docs/api/lecturer',
            ],
            'consumer_paths' => [
                'FE/lecturer-nuxt/shared/types',
                'FE/lecturer-nuxt/app/composables',
                'FE/lecturer-nuxt/app/stores',
                'FE/lecturer-nuxt/app/pages',
            ],
        ],
    ],
];
