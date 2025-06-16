# Development Checklist & Guidelines

This document provides a comprehensive, actionable checklist for developers implementing new pages, features, or editing source code in this Laravel + Vue.js + Inertia.js codebase.

## 🏗️ Code Structure & Architecture

### Backend Structure
- [ ] **Controllers**: Place controllers in appropriate folders under `app/Http/Controllers/`
  - [ ] Single-purpose controllers in root directory
  - [ ] Multi-controller features grouped in folders (e.g., `Users/`, `Settings/`)
- [ ] **Models**: Store in `app/Models/` with proper relationships and validation methods
- [ ] **Routes**: Organize routes in separate files by feature (e.g., `routes/users.php`, `routes/programs.php`)
- [ ] **Middleware**: Apply appropriate middleware for authentication and permissions

### Frontend Structure
- [ ] **Pages**: Store in `resources/js/pages/[feature]/` directory
- [ ] **Components**: Reusable components in `resources/js/components/`
- [ ] **Layouts**: Use `AppLayout.vue` for authenticated pages, `AuthLayout.vue` for auth pages
- [ ] **Types**: All TypeScript interfaces in `resources/js/types/` directory
- [ ] **Composables**: Reusable logic in `resources/js/composables/`

## 📝 Naming Conventions

### Files & Directories
- [ ] **Controllers**: PascalCase with `Controller` suffix (e.g., `UserController.php`)
- [ ] **Models**: PascalCase singular (e.g., `User.php`, `CurriculumVersion.php`)
- [ ] **Vue Components**: PascalCase (e.g., `DataTable.vue`, `UserProfile.vue`)
- [ ] **Vue Pages**: PascalCase (e.g., `Index.vue`, `Create.vue`, `Edit.vue`)
- [ ] **Routes**: kebab-case (e.g., `/curriculum-versions`, `/specializations`)

### Variables & Functions
- [ ] **PHP**: camelCase for methods, snake_case for properties
- [ ] **TypeScript/JavaScript**: camelCase for variables and functions
- [ ] **Vue Props**: camelCase in script, kebab-case in templates
- [ ] **Database**: snake_case for table and column names

### Constants & Types
- [ ] **TypeScript Interfaces**: PascalCase (e.g., `User`, `PaginatedResponse`)
- [ ] **Validation Rules**: camelCase keys in `ValidationRules` object
- [ ] **Route Names**: dot notation (e.g., `users.index`, `specializations.create`)

## 📋 Form Validation Standards

### Frontend Validation (MANDATORY)
- [ ] **Always use shadcn-vue forms** with vee-validate and Zod schemas
- [ ] **Import required components**:
  ```vue
  import { useForm } from 'vee-validate'
  import { toTypedSchema } from '@vee-validate/zod'
  import { z } from 'zod'
  import { FormField, FormItem, FormLabel, FormControl, FormMessage } from '@/components/ui/form'
  ```
- [ ] **Define validation schema** using Zod with proper error messages
- [ ] **Use FormField components** for consistent form structure
- [ ] **Handle form submission** with proper loading states and error handling

### Backend Validation (MANDATORY)
- [ ] **Step 1**: Define validation rules in Model using static methods:
  ```php
  public static function validationRules(): array
  public static function validationMessages(): array
  ```
- [ ] **Step 2**: Create Form Request classes that use model validation rules
- [ ] **Step 3**: Sync validation constants in `resources/js/types/validation.ts`
- [ ] **Ensure consistency** between frontend Zod schemas and backend Laravel rules

## 🔧 Input Components Standards

### DebouncedInput Usage (MANDATORY)
- [ ] **Always use DebouncedInput** for search and filter inputs
- [ ] **Never use watch()** for API calls or data fetching
- [ ] **Never use setTimeout()** for debouncing
- [ ] **Import and configure properly**:
  ```vue
  import DebouncedInput from '@/components/DebouncedInput.vue'
  ```

### Select Component Guidelines (CRITICAL)
- [ ] **NEVER use empty string values** in `<SelectItem />` components
- [ ] **Use non-empty values** like `"all"` for "All" options
- [ ] **Initialize filters** with `"all"` instead of empty strings
- [ ] **Convert values** when sending to backend (`"all"` → empty string)
- [ ] **Handle props initialization** using `|| 'all'` fallback

## 📊 Table Display Standards

### DataTable Implementation (MANDATORY)
- [ ] **Always use DataTable.vue** and **DataPagination.vue** for tabular data
- [ ] **Define proper column definitions** with TypeScript types
- [ ] **Implement proper pagination** handling with preserveState and preserveScroll
- [ ] **Add action columns** using cell templates
- [ ] **Include loading states** and error handling

### Required Table Features
- [ ] **Pagination**: Server-side pagination with proper URL parameters
- [ ] **Filtering**: Column-specific filters with debounced input
- [ ] **Search**: Global search functionality
- [ ] **Actions**: Edit, delete, and bulk operations
- [ ] **Export**: Excel export functionality where applicable

## 🎯 Type Organization (MANDATORY)

### Central Type Management
- [ ] **Store ALL interfaces** in `resources/js/types/` directory
- [ ] **Organize by purpose**:
  - `models.ts` - Database model interfaces
  - `api.ts` - API response types
  - `forms.ts` - Form data types
  - `components.ts` - Component prop types
  - `validation.ts` - Validation rule constants
- [ ] **Export from index.ts** for clean imports
- [ ] **Use proper TypeScript syntax** with optional properties and unions

### Interface Standards
- [ ] **Match backend model structure** exactly
- [ ] **Include relationships** as optional properties
- [ ] **Use proper date types** (string for ISO dates)
- [ ] **Define union types** for enums and status fields

## 🎛️ Controller Organization

### Multi-Controller Features
- [ ] **Group related controllers** in folders under `app/Http/Controllers/`
- [ ] **Example structure**:
  ```
  app/Http/Controllers/
  ├── Users/
  │   ├── UserController.php
  │   ├── UserExportController.php
  │   └── UserImportController.php
  ```

### Route Organization
- [ ] **Use route groups** with proper prefixes and middleware
- [ ] **Apply permissions middleware** to each route
- [ ] **Follow RESTful conventions** for resource routes
- [ ] **Separate API routes** for bulk operations

## 🧩 Component Standards

### Vue Component Structure
- [ ] **Use Composition API** with `<script setup lang="ts">`
- [ ] **Define proper TypeScript interfaces** for props and emits
- [ ] **Use withDefaults()** for optional props
- [ ] **Import types** from central type files

### Component Props Pattern
```vue
<script setup lang="ts">
import type { User } from '@/types'

interface Props {
  user: User
  showActions?: boolean
  variant?: 'default' | 'compact'
}

const props = withDefaults(defineProps<Props>(), {
  showActions: true,
  variant: 'default'
})

interface Emits {
  edit: [user: User]
  delete: [userId: number]
}

const emit = defineEmits<Emits>()
</script>
```

### Composable Usage
- [ ] **Create reusable logic** in `resources/js/composables/`
- [ ] **Use readonly()** for exposed reactive state
- [ ] **Return consistent interface** with loading, error, and data states
- [ ] **Handle API calls** with proper error handling

## 📚 Documentation Requirements

### Inline Comments
- [ ] **Document complex business logic** with clear comments
- [ ] **Explain validation rules** and their business purpose
- [ ] **Document API endpoints** with expected parameters and responses
- [ ] **Add JSDoc comments** for TypeScript functions and interfaces

### Code Documentation
- [ ] **Update README files** when adding new features
- [ ] **Document API changes** in relevant documentation files
- [ ] **Include usage examples** for new components or composables
- [ ] **Document environment variables** and configuration changes

## 🧪 Testing Standards

### Required Test Types
- [ ] **Feature Tests**: Test complete user workflows
- [ ] **Unit Tests**: Test individual functions and methods
- [ ] **Component Tests**: Test Vue component behavior
- [ ] **API Tests**: Test API endpoints and responses

### Test Structure (Using Pest)
- [ ] **Use describe/it structure** for organized test suites
- [ ] **Test happy path** and error scenarios
- [ ] **Use factories** for test data generation
- [ ] **Test validation rules** and error messages
- [ ] **Test permissions** and authorization

### Test Coverage Expectations
- [ ] **Controllers**: Test all public methods and edge cases
- [ ] **Models**: Test relationships, scopes, and custom methods
- [ ] **Form Requests**: Test validation rules and authorization
- [ ] **API Endpoints**: Test all HTTP methods and status codes

### Example Test Pattern
```php
describe('UserController', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    describe('index', function () {
        it('displays users index page', function () {
            $response = $this->actingAs($this->user)->get('/users');

            $response->assertOk();
            $response->assertInertia(
                fn($page) => $page->component('users/Index')
            );
        });
    });
});
```

## ⚠️ Error Handling Standards

### Frontend Error Handling
- [ ] **Use try-catch blocks** for async operations
- [ ] **Display user-friendly error messages** using toast notifications
- [ ] **Handle form validation errors** with proper field highlighting
- [ ] **Implement loading states** during API calls
- [ ] **Log errors** to console for debugging

### Backend Error Handling
- [ ] **Use Form Requests** for validation with custom error messages
- [ ] **Return consistent error responses** with proper HTTP status codes
- [ ] **Log errors** using Laravel's logging system
- [ ] **Handle database exceptions** gracefully
- [ ] **Validate permissions** before performing operations

### Error Response Pattern
```php
// Controller error handling
try {
    // Operation logic
    return redirect()->route('users.index')
        ->with('success', 'User created successfully');
} catch (\Exception $e) {
    Log::error('User creation failed', ['error' => $e->getMessage()]);
    return back()->withErrors(['error' => 'Failed to create user']);
}
```

## 🚀 Performance Considerations

### Database Optimization
- [ ] **Use eager loading** to prevent N+1 queries
- [ ] **Implement proper indexing** on frequently queried columns
- [ ] **Use pagination** for large datasets
- [ ] **Optimize query scopes** and relationships
- [ ] **Use database transactions** for multi-step operations

### Frontend Performance
- [ ] **Implement lazy loading** for large components
- [ ] **Use computed properties** instead of methods for reactive data
- [ ] **Debounce user inputs** for search and filters
- [ ] **Minimize API calls** with proper caching strategies
- [ ] **Use preserveState and preserveScroll** for better UX

### Caching Strategy
- [ ] **Cache frequently accessed data** (roles, permissions, settings)
- [ ] **Use Redis** for session and cache storage in production
- [ ] **Implement query result caching** where appropriate
- [ ] **Cache API responses** on the frontend when suitable

## 🔒 Security Requirements

### Authentication & Authorization
- [ ] **Verify user authentication** on all protected routes
- [ ] **Implement permission checks** using middleware
- [ ] **Validate user permissions** in controllers
- [ ] **Use CSRF protection** for all forms
- [ ] **Implement rate limiting** on API endpoints

### Data Validation & Sanitization
- [ ] **Validate all user inputs** on both frontend and backend
- [ ] **Sanitize data** before database storage
- [ ] **Use parameterized queries** (Laravel ORM handles this)
- [ ] **Validate file uploads** with proper type and size checks
- [ ] **Escape output** to prevent XSS attacks

### Security Checklist
- [ ] **Use HTTPS** in production
- [ ] **Implement proper session management**
- [ ] **Validate API tokens** for API endpoints
- [ ] **Log security events** (failed logins, permission violations)
- [ ] **Regular security updates** for dependencies

## ✅ Code Review Checklist

### Before Submitting Code
- [ ] **Run all tests** and ensure they pass
- [ ] **Check code formatting** with Prettier and ESLint
- [ ] **Verify TypeScript compilation** without errors
- [ ] **Test functionality** in browser manually
- [ ] **Check for console errors** and warnings

### Code Quality Checks
- [ ] **Follow naming conventions** consistently
- [ ] **Remove commented-out code** and debug statements
- [ ] **Ensure proper error handling** is implemented
- [ ] **Verify responsive design** on different screen sizes
- [ ] **Check accessibility** features (ARIA labels, keyboard navigation)

### Performance Checks
- [ ] **No N+1 queries** in database operations
- [ ] **Proper pagination** for large datasets
- [ ] **Optimized images** and assets
- [ ] **Minimal bundle size** impact
- [ ] **Fast page load times**

### Security Review
- [ ] **All inputs validated** and sanitized
- [ ] **Proper permission checks** implemented
- [ ] **No sensitive data** exposed in frontend
- [ ] **CSRF protection** enabled
- [ ] **SQL injection prevention** (using ORM)

## 🔗 Integration Guidelines

### API Integration
- [ ] **Use consistent API response format** with data, message, and errors
- [ ] **Implement proper HTTP status codes** (200, 201, 400, 401, 403, 404, 500)
- [ ] **Version API endpoints** when making breaking changes
- [ ] **Document API endpoints** with request/response examples
- [ ] **Use resource transformers** for consistent data formatting

### Third-Party Integrations
- [ ] **Store API keys** in environment variables
- [ ] **Implement proper error handling** for external API failures
- [ ] **Add timeout configurations** for external requests
- [ ] **Log integration events** for debugging
- [ ] **Use queues** for non-critical external API calls

### Database Integration
- [ ] **Use migrations** for all database schema changes
- [ ] **Create seeders** for default data
- [ ] **Implement proper foreign key constraints**
- [ ] **Use soft deletes** where data retention is required
- [ ] **Create database backups** before major changes

## 🚀 Deployment Considerations

### Environment Configuration
- [ ] **Set proper environment variables** for production
- [ ] **Configure database connections** correctly
- [ ] **Set up proper logging** levels and channels
- [ ] **Configure cache drivers** (Redis for production)
- [ ] **Set up queue workers** for background jobs

### Build Process
- [ ] **Run production build** for frontend assets
- [ ] **Optimize images** and static assets
- [ ] **Enable asset versioning** for cache busting
- [ ] **Minify CSS and JavaScript** files
- [ ] **Generate source maps** for debugging

### Security Configuration
- [ ] **Set secure session configuration**
- [ ] **Configure CORS** properly for API endpoints
- [ ] **Enable HTTPS** and redirect HTTP traffic
- [ ] **Set proper file permissions** on server
- [ ] **Configure firewall rules** for database access

### Monitoring & Maintenance
- [ ] **Set up error monitoring** (e.g., Sentry, Bugsnag)
- [ ] **Configure performance monitoring**
- [ ] **Set up automated backups**
- [ ] **Monitor disk space** and server resources
- [ ] **Plan for regular updates** and maintenance windows

## 📋 Quick Reference Checklists

### New Page Implementation
1. [ ] Create controller with proper validation
2. [ ] Define routes with middleware
3. [ ] Create TypeScript interfaces
4. [ ] Build Vue page component
5. [ ] Implement form validation (frontend + backend)
6. [ ] Add table with DataTable component
7. [ ] Write comprehensive tests
8. [ ] Update navigation/breadcrumbs
9. [ ] Test permissions and authorization
10. [ ] Review and optimize performance

### New Feature Implementation
1. [ ] Plan database schema changes
2. [ ] Create/update models with relationships
3. [ ] Write migrations and seeders
4. [ ] Implement backend logic
5. [ ] Create frontend components
6. [ ] Add validation rules
7. [ ] Write tests for all scenarios
8. [ ] Update documentation
9. [ ] Test integration points
10. [ ] Deploy and monitor

### Bug Fix Checklist
1. [ ] Reproduce the issue locally
2. [ ] Write failing test case
3. [ ] Implement fix
4. [ ] Verify test passes
5. [ ] Test related functionality
6. [ ] Check for regression issues
7. [ ] Update documentation if needed
8. [ ] Deploy fix
9. [ ] Monitor for related issues
10. [ ] Update issue tracking

## 🛠️ Development Tools & Commands

### Useful Laravel Commands
```bash
# Generate controller
php artisan make:controller Users/UserController

# Generate model with migration
php artisan make:model User -m

# Generate form request
php artisan make:request StoreUserRequest

# Run tests
php artisan test

# Generate factory
php artisan make:factory UserFactory
```

### Frontend Development
```bash
# Install dependencies
npm install

# Development server
npm run dev

# Build for production
npm run build

# Type checking
npm run type-check

# Linting
npm run lint
```

### Testing Commands
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/UserControllerTest.php

# Run tests with coverage
php artisan test --coverage
```

---

## 📖 Additional Resources

- **Laravel Documentation**: https://laravel.com/docs
- **Vue.js Documentation**: https://vuejs.org/guide/
- **Inertia.js Documentation**: https://inertiajs.com/
- **Tailwind CSS Documentation**: https://tailwindcss.com/docs
- **shadcn/ui Vue Documentation**: https://www.shadcn-vue.com/

Remember to always follow the established patterns in the codebase and refer to existing implementations as examples when implementing new features.
