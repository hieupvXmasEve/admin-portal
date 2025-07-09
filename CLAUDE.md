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
Business logic is organized in service classes:
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
1. **Authentication**: Laravel Sanctum with multiple guards (web, student, api)
2. **Campus Context**: Middleware ensuring campus selection
3. **Role-Based Permissions**: Campus-specific role assignments using Spatie Laravel Permission
4. **Frontend Guards**: Vue directives for UI authorization

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
- **UI Components**: Use reka-ui (shadcn/ui) consistently
- **Data Tables**: Always use `DataTable.vue` + `DataPagination.vue` + `DebouncedInput.vue`
- **Form Components**: Use vee-validate + Zod schemas + reka-ui form components
- **Type Safety**: TypeScript interfaces in `resources/js/types/`

### Critical Frontend Patterns
1. **SelectItem Values**: Never use empty string `value=""` - use `"none"` or meaningful values
2. **Form Validation**: Zod schemas must align with Laravel validation rules
3. **Data Transformation**: Transform form data before submission (strings to numbers, null handling)
4. **Error Handling**: Comprehensive error logging and user feedback

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

### TypeScript Standards
- **Strict Mode**: TypeScript strict mode enabled
- **Interface First**: Define interfaces before implementation
- **Type Organization**: All types in `resources/js/types/`
- **Satisfies Keyword**: Use `satisfies` for compile-time type checking

### Testing Standards
- **Pest PHP**: Modern testing framework for PHP
- **Factory Pattern**: Model factories for test data
- **Database Transactions**: Clean test isolation
- **Feature Tests**: End-to-end functionality testing

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
- `.cursor/rules/global_rules.mdc` - Core development principles
- `.cursor/rules/development-standards.mdc` - Detailed coding standards

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
