# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Primary Development Command
```bash
composer dev
```
This starts the complete development environment with color-coded output:
- Laravel development server (`php artisan serve`)
- Queue worker (`php artisan queue:listen --tries=1`)
- Log viewer (`php artisan pail --timeout=0`)
- Vite development server (`npm run dev`)

### Testing Commands
```bash
# Quick local tests
./scripts/pre-push.sh

# Full Docker integration tests
./scripts/test-local.sh
npm run test:local

# Pre-push validation with Docker
./scripts/pre-push.sh --docker
npm run pre-push:docker

# PHP tests only
composer test
php artisan test
./vendor/bin/pest

# TypeScript type checking
npm run type-check
npx vue-tsc --noEmit
```

### Build Commands
```bash
# Frontend development
npm run dev                # Development with HMR
npm run build              # Production build
npm run build:ssr          # SSR production build

# PHP code formatting
./vendor/bin/pint          # Auto-format PHP code

# Frontend linting
npm run lint               # ESLint with auto-fix
npm run format             # Prettier formatting
npm run format:check       # Check formatting
```

### Docker Commands
```bash
# Development environment
./dev.sh start
./dev.sh stop

# Local production testing
./local-prod.sh start

# Production deployment
./prod.sh deploy
```

## High-Level Architecture

### Tech Stack
- **Backend**: Laravel 12, PHP 8.4, FrankenPHP, MySQL 8.0, Redis
- **Frontend**: Vue.js 3, TypeScript, Inertia.js, TailwindCSS 4.x
- **UI Components**: reka-ui Vue, @tanstack/vue-table
- **Form Validation**: vee-validate + Zod schemas
- **State Management**: Pinia stores
- **Development**: Docker, Vite HMR, Hot reload

### Multi-Campus Architecture
The system is designed as a multi-campus educational management platform:
- **Campus Selection**: Users select a campus context that persists across sessions
- **Campus Isolation**: Data is filtered by campus context throughout the application
- **Middleware**: `CheckCampusSelected` middleware ensures campus context exists
- **Role-Based Access**: Permissions are campus-specific

### Service Layer Pattern
Business logic is organized in service classes following the Service-Request-Resource Pattern:
```php
// Example: StudentService handles complex student operations
class StudentService
{
    public function createAdmittedStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            // Complex business logic with transactions
        });
    }
}
```

#### Service-Request-Resource Pattern
For each module, implement the following structure:
1. **Service** (in `app/Services/`) - Business logic, entity operations, logging
2. **Form Request** (in `app/Http/Requests/Module/`) - Validation rules
3. **API Resource** (in `app/Http/Resources/Module/`) - JSON response formatting
4. **Web Controller** (in `app/Http/Controllers/Web/`) - Uses FormRequests, calls Service, returns Inertia views
5. **API Controller** (in `app/Http/Controllers/Api/`) - Uses FormRequests, calls Service, returns Resource responses

### Database Architecture
Core academic entities follow this hierarchy:
```
Campuses → Buildings → Rooms
Programs → Specializations → Curriculum Versions → Curriculum Units
Semesters → Course Offerings → Class Schedules
Users → Students/Faculty/Staff
```

Key patterns:
- **Soft Deletes**: Used for data integrity
- **Eloquent Relationships**: Well-defined relationships with proper constraints
- **Model Validation**: Static validation rules in models
- **Database Transactions**: Complex operations wrapped in transactions

### Authentication & Authorization
Multi-layered security system:
1. **Authentication**: Laravel Sanctum + Socialite with multiple guards (web, student, api)
2. **Campus Context**: Middleware ensuring campus selection
3. **Role-Based Permissions**: Campus-specific role assignments using Spatie Laravel Permission
4. **Frontend Guards**: Vue directives for UI authorization
5. **Permission System**: Centralized definitions in `config/permission.php` with automatic gate registration

### API Design
Dual response strategy for controllers:
```php
// Controllers handle both web (Inertia) and API (JSON) requests
public function store(Request $request): RedirectResponse|JsonResponse
{
    $model = $this->service->create($request->validated());
    
    if ($request->expectsJson()) {
        return new ModelResource($model);
    }
    
    return redirect()->route('models.index');
}
```

## Frontend Architecture

### Component Standards
- **UI Components**: Use reka-ui (shadcn-vue) consistently
- **Data Tables**: Always use `DataTable.vue` + `DataPagination.vue` + `DebouncedInput.vue`
- **Form Components**: Use vee-validate + Zod schemas + reka-ui form components
- **Type Safety**: TypeScript interfaces in `resources/js/types/`
- **Structure**: Use `<script setup>` syntax for all new components
- **Naming**: PascalCase for components, kebab-case for templates
- **Composition API**: Prefer over options API for new components

### DataTable and DataPagination Usage Pattern
Follow this standardized pattern for all data table implementations:

#### Interface and Props
```typescript
import type { PaginatedResponse } from '@/types';

interface Props {
    items: PaginatedResponse<ModelType>;
    filters?: {
        search?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
        // ... other filters
    };
}
```

#### Filter State Management
```typescript
// Filter state - Initialize with props or defaults
const filters = ref({
    search: props.filters?.search || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
    // ... other filters with defaults
});

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();

    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `/resource-path${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['items', 'filters'],
    });
};

// Search handler for DebouncedInput
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
        // ... reset other filters to defaults
    };
    router.visit('/resource-path', {
        preserveState: true,
        preserveScroll: true,
        only: ['items', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return filters.value.search || /* other filter conditions */;
});
```

#### Column Definitions
```typescript
const columns: ColumnDef<ModelType>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.items.current_page;
            const perPage = props.items.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Column Name',
        accessorKey: 'field_name',
        enableSorting: true,
        cell: ({ row }) => {
            const item = row.original;
            return h('div', { class: 'font-medium' }, item.field_name);
        },
    },
    // ... other columns
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: 'actions', // Use template slot
    },
];
```

#### Pagination Handlers
```typescript
// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['items'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
```

#### Template Structure
```vue
<template>
    <!-- Filters Section -->
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <DebouncedInput 
                placeholder="Search..." 
                v-model="filters.search" 
                @debounced="handleSearch" 
            />
        </div>
        
        <!-- Additional filters as Select components -->
        
        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns">
        <template #cell-actions="{ row }">
            <div class="flex items-center gap-2">
                <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="ghost" size="sm" @click="action(row.original)">
                                <Icon class="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Action description</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination 
        :pagination-data="items" 
        @navigate="handlePaginationNavigate" 
        @page-size-change="handlePageSizeChange" 
    />
</template>
```

#### Key Principles
- Use `PaginatedResponse<T>` type for consistent pagination structure
- Always implement server-side filtering with `applyFilters()`
- Use `DebouncedInput` for search with `@debounced` event
- Define actions column with `cell: 'actions'` and use template slots
- Implement pagination handlers for navigation and page size changes
- Use `preserveState: true` and `preserveScroll: true` for better UX
- Use `only: ['items', 'filters']` to optimize network requests

### Critical Frontend Patterns
1. **SelectItem Values**: Never use empty string `value=""` - use `"none"` or meaningful values
2. **Form Validation**: Zod schemas must align with Laravel validation rules
3. **Data Transformation**: Transform form data before submission (strings to numbers, null handling)
4. **Error Handling**: Comprehensive error logging and user feedback
5. **Component Installation**: Add TODO comments for missing reka-ui components: `<!-- TODO: Run npx shadcn-vue add [component] -->`
6. **Template Guidelines**: Use `v-show` for frequent toggles, `v-if` for rare changes, always use `:key` with `v-for`

### State Management
- **Pinia**: For complex state management
- **Composables**: For reusable reactive logic
- **Inertia Props**: For server-side data injection

## Development Standards

### PHP Standards
- **PSR-12**: Follow PSR-12 coding standards
- **Strict Types**: Always use `declare(strict_types=1)`
- **Laravel Pint**: Auto-format with `./vendor/bin/pint`
- **Service Layer**: Business logic in service classes, not controllers
- **Controllers**: Keep thin - delegate to services, use form requests for validation
- **Database**: Use snake_case for columns/tables, PascalCase for models
- **Routes**: Use RESTful conventions with proper namespacing

### TypeScript Standards
- **Strict Mode**: TypeScript strict mode enabled
- **Interface First**: Define interfaces before implementation
- **Type Organization**: All types in `resources/js/types/`
- **Satisfies Keyword**: Use `satisfies` for compile-time type checking
- **Explicit Types**: Function parameters and return types must be explicitly typed
- **Imports**: Use `import type` for type-only imports
- **Constants**: Prefer `const` over `let`, avoid `var` entirely

### Testing Standards
- **Pest PHP**: Modern testing framework for PHP
- **Factory Pattern**: Model factories for test data
- **Database Transactions**: Clean test isolation
- **Feature Tests**: End-to-end functionality testing
- **Unit Tests**: Required for new services
- **Integration Tests**: Required for new API endpoints
- **Permission Tests**: Test authorization controls

### Code Quality Standards
- **Linting**: All code must pass ESLint and Prettier validation
- **Type Safety**: All TypeScript code must have explicit types
- **Error Handling**: Use try-catch blocks with meaningful error messages
- **Documentation**: TODO comments required for missing components, routes, migrations
- **Validation**: Consistent validation between frontend (Zod) and backend (Laravel)
- **Database Fields**: ALWAYS verify model fields exist in database schema before using them - never assume fields exist

### Naming Conventions
- **Backend**: PascalCase for controllers/models/services, snake_case for database
- **Frontend**: PascalCase for components, camelCase for functions/variables
- **Routes**: kebab-case for URLs, dot notation for route names
- **Files**: Match content naming (UserController.php, UserService.php, User.vue)

### Directory Structure Requirements
- **Controllers**: Separate Web (`app/Http/Controllers/Web/`) and API (`app/Http/Controllers/Api/`) controllers
- **Services**: Business logic in `app/Services/` directory
- **Form Requests**: Validation in `app/Http/Requests/Module/` directories
- **Resources**: API responses in `app/Http/Resources/Module/` directories
- **Frontend**: Components in `resources/js/components/`, types in `resources/js/types/`

## Key File Locations

### Configuration Files
- `composer.json` - PHP dependencies and scripts
- `package.json` - Frontend dependencies and scripts
- `vite.config.ts` - Vite configuration
- `tailwind.config.js` - TailwindCSS configuration
- `eslint.config.js` - ESLint configuration
- `tsconfig.json` - TypeScript configuration

### Development Rules
- `.cursor/rules/` - Development standards and patterns
- `.cursor/rules/code-generation.mdc` - Code generation and quality standards
- `.cursor/rules/service-request-resource-pattern.mdc` - Service layer architecture
- `.cursor/rules/structure.mdc` - Project structure and organization
- `.cursor/rules/tech.mdc` - Technology stack and build system

### Docker Configuration
- `docker-compose.yml` - Base Docker configuration
- `docker-compose.dev.yml` - Development environment
- `docker-compose.prod.yml` - Production environment
- `./dev.sh`, `./local-prod.sh`, `./prod.sh` - Environment scripts

## Common Patterns

### Controller Pattern
```php
class ResourceController extends Controller
{
    public function __construct(
        protected ResourceService $service
    ) {}
    
    public function index(Request $request): Response
    {
        $query = Resource::with(['relations'])
            ->orderBy('created_at', 'desc');
            
        // Apply filters
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        
        return Inertia::render('Resources/Index', [
            'resources' => $query->paginate(15),
            'filters' => $request->only(['search']),
        ]);
    }
}
```

### Vue Component Pattern
```vue
<script setup lang="ts">
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

const formSchema = toTypedSchema(z.object({
    name: z.string().min(1, 'Name is required'),
    // ... other fields
}))

const { handleSubmit, isSubmitting } = useForm({
    validationSchema: formSchema,
    initialValues: {
        name: '',
        // ... other fields
    }
})

const onSubmit = handleSubmit((values) => {
    router.post('/resources', values, {
        onSuccess: () => console.log('Success'),
        onError: (errors) => console.error('Errors:', errors)
    })
})
</script>
```

### Service Pattern
```php
class ResourceService
{
    public function create(array $data): Resource
    {
        return DB::transaction(function () use ($data) {
            $resource = Resource::create($data);
            
            // Additional business logic
            $this->handleRelatedOperations($resource);
            
            return $resource;
        });
    }
}
```

## Import/Export Functionality
The system includes comprehensive import/export capabilities:
- **Excel Integration**: Uses `maatwebsite/excel` package
- **Bulk Operations**: Support for bulk create/update/delete
- **Validation**: Client-side and server-side validation during import
- **Error Handling**: Detailed error reporting for failed imports

## Deployment Environments
- **Development**: HTTP-only, debug enabled, hot reload
- **Local Production**: HTTPS testing, production-like environment
- **Production**: Full HTTPS, SSL certificates, optimized performance

## Database Management
- **Migrations**: Well-structured with proper dependencies
- **Seeders**: Organized in timeline-based approach
- **Foreign Keys**: Proper constraints with cascade rules
- **Indexing**: Appropriate indexes for performance

Run these commands when working with the codebase:
- **Setup**: `composer dev` for development
- **Test**: `./scripts/pre-push.sh` before pushing
- **Build**: `npm run build` for production
- **Lint**: `./vendor/bin/pint` and `npm run lint`
