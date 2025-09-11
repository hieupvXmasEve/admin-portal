# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Quick Start Commands

### Development
```bash
# Start all development services (server, queue, logs, vite)
composer dev

# Start individual services
php artisan serve              # Laravel development server
npm run dev                   # Vite frontend dev server with HMR
php artisan queue:listen      # Queue worker
php artisan pail              # Log viewer
```

### Testing
```bash
# Individual test commands
php artisan test              # Run PHP tests
./vendor/bin/pest            # Pest testing framework
npm run type-check           # TypeScript validation
npm run lint                 # ESLint
npm run format:check         # Prettier check
```

### Build & Deploy
```bash
# Frontend build
npm run build                # Production build
npm run build:ssr            # Server-side rendering build

# Code quality
./vendor/bin/pint            # PHP code formatting
npm run format               # Frontend formatting
```

### Database
```bash
# Run migrations
php artisan migrate
php artisan migrate:fresh --seed

# Generate routes
php artisan ziggy:generate   # Frontend route definitions
```

## Architecture Overview

### Tech Stack
- **Backend**: Laravel 12 + PHP 8.4 + FrankenPHP
- **Frontend**: Vue.js 3 + TypeScript + Inertia.js + TailwindCSS
- **UI Components**: Reka-UI (shadcn-vue), TanStack Table
- **Database**: MySQL 8.0 + Redis
- **Authentication**: Laravel Sanctum + Socialite (multi-guard: web, student, api)
- **Permissions**: Spatie Laravel Permission with campus-scoped authorization

### Service-Request-Resource Pattern
The application follows a strict architectural pattern:
- **Services** (`app/Services/`): Business logic and transactions
- **FormRequests** (`app/Http/Requests/`): Input validation
- **API Resources** (`app/Http/Resources/`): JSON response formatting
- **Controllers**: Thin layer that delegates to services and returns resources

Controllers should only:
1. Call appropriate service methods
2. Return formatted responses (via Resources) or redirects
3. Never contain business logic or direct database queries

### Multi-Campus System
- All operations are campus-scoped via `CheckCampusSelected` middleware
- Campus context is required for all authenticated routes
- Permissions and roles are campus-specific
- Database queries automatically filter by current campus

### Frontend Architecture
- **Pages**: Vue SFCs in `resources/js/pages/` organized by feature
- **Components**: Reusable components in `resources/js/components/`
- **Composables**: Shared logic in `resources/js/composables/`
- **Stores**: Pinia stores in `resources/js/stores/`
- **Types**: TypeScript interfaces in `resources/js/types/`

### Permission System
- Centralized definitions in `config/permission.php`
- Automatic gate registration via `PermissionServiceProvider`
- Route permission helpers for consistent authorization
- Campus-scoped permission checking

### Data Tables Pattern
All data tables use the standard `DataTable.vue` component with:
- Server-side pagination, sorting, and filtering
- Selection support via `createColumns()` with `{ enableSelection: true }`
- Consistent API response format
- `applyFilters()` method for URL parameter management

## Development Workflow

### Code Quality Standards
- **PHP**: Laravel Pint for code formatting
- **Frontend**: ESLint + Prettier + TypeScript strict mode
- **Testing**: Pest for PHP, comprehensive integration tests
- **Validation**: Consistent validation rules between frontend (Zod) and backend (Form Requests)

### Form Handling
- Backend validation via Laravel Form Request classes
- Frontend validation using Vee-validate + Zod schemas
- Form data type conversion before submission (string→number/null)
- Use `preserveState` & `preserveScroll` in Inertia visits

### API Integration
- Use `useApi()` composable for API requests. `import { useApi } from '@/composables/useApiRequest';`
- Pattern: `const api = useApi(); const { data } = await api.get('/endpoint', params)`
- Handle errors with try/catch and toast notifications. `import { toast } from 'vue-sonner';`
- Consistent JSON response formatting via API Resources

### Testing Strategy
- **Unit Tests**: For services and business logic
- **Feature Tests**: For API endpoints and integration
- **Browser Tests**: For critical user workflows
- **Transactional Tests**: For database isolation

## Key Directories

### Backend Structure
```
app/
├── Console/Commands/     # Custom Artisan commands
├── Constants/           # Route constants and system constants
├── Http/
│   ├── Controllers/     # Feature-organized controllers
│   ├── Middleware/      # Custom middleware
│   ├── Requests/        # Form validation classes
│   └── Resources/       # API response transformers
├── Models/             # Eloquent models
├── Services/           # Business logic services
└── Helpers/            # Helper functions and utilities
```

### Frontend Structure
```
resources/js/
├── components/         # Reusable Vue components
├── composables/        # Vue composition functions
├── pages/             # Feature-organized page components
├── stores/            # Pinia state management
├── types/             # TypeScript interfaces
├── utils/             # Utility functions
└── app.ts             # Application entry point
```

### Configuration & Scripts
- **scripts/**: Development and deployment scripts
- **docker/**: Docker configuration files
- **routes/web/**: Feature-organized route files
- **config/permission.php**: Centralized permission definitions

## Important Notes

### Database & Models
- All models use soft deletes unless specified otherwise
- Campus context filtering is applied automatically
- Relationships must match actual Eloquent model definitions
- Always verify field names against actual database schema

### Security & Authentication
- Multi-guard authentication system (web, student, api)
- Campus-scoped permissions throughout the application
- CSRF protection on web routes
- API authentication via Sanctum tokens

### Performance Considerations
- Use eager loading for relationships to avoid N+1 queries
- Redis caching for frequently accessed data
- Queue system for background tasks
- Asset optimization via Vite build system

## Environment Setup

### Required Files
- `.env` - Application environment configuration
- `.env.docker.dev` - Docker development environment
- `env.docker.example` - Environment template

### Services
- **Application**: http://localhost:8080
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

The application includes comprehensive validation scripts and automated testing pipelines to ensure code quality and deployment readiness.
