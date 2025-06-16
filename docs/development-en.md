# 🛠️ Development Guide

Comprehensive development guide for the Swinburne Project Management System built with Laravel 12 + Vue.js 3 + Inertia.js.

## 🏗️ Architecture Overview

### Tech Stack
- **Backend**: Laravel 12, PHP 8.4
- **Frontend**: Vue.js 3, TypeScript, TailwindCSS
- **Web Server**: FrankenPHP (modern PHP application server)
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Development**: Docker Compose, Vite HMR

### Project Structure
```
swinx/
├── app/
│   ├── Http/Controllers/     # Controllers organized by feature
│   ├── Models/              # Eloquent models
│   ├── Services/            # Business logic services
│   └── Policies/            # Authorization policies
├── resources/
│   ├── js/
│   │   ├── pages/           # Vue pages by feature
│   │   ├── components/      # Reusable Vue components
│   │   ├── types/           # TypeScript interfaces
│   │   └── composables/     # Reusable logic
├── routes/                  # Route definitions by feature
├── database/
│   ├── migrations/          # Database migrations
│   └── seeders/            # Database seeders
└── docs/                   # Project documentation
```

## 📋 Development Standards

### Code Structure
- **Controllers**: Group related controllers in folders (e.g., `Users/`, `Settings/`)
- **Models**: Store in `app/Models/` with proper relationships
- **Routes**: Organize in separate files by feature
- **Frontend**: Use Composition API with TypeScript

### Naming Conventions
- **Controllers**: PascalCase with `Controller` suffix
- **Models**: PascalCase singular (e.g., `User.php`)
- **Vue Components**: PascalCase (e.g., `DataTable.vue`)
- **Routes**: kebab-case (e.g., `/curriculum-versions`)
- **Database**: snake_case for tables and columns

### Form Validation (MANDATORY)
- **Frontend**: Always use shadcn-vue forms with vee-validate and Zod schemas
- **Backend**: Define validation rules in Model using static methods
- **Consistency**: Sync validation between frontend and backend

### Component Standards
- **DebouncedInput**: Always use for search and filter inputs
- **DataTable**: Use DataTable.vue and DataPagination.vue for tabular data
- **Select Components**: Never use empty string values in SelectItem

## 🎯 Type Management

### Central Type Organization (MANDATORY)
Store ALL interfaces in `resources/js/types/` directory:

```typescript
// resources/js/types/models.ts
export interface User {
  id: number
  name: string
  email: string
  created_at: string
  updated_at: string
}

// resources/js/types/api.ts
export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    total: number
    per_page: number
  }
}
```

### Interface Standards
- Match backend model structure exactly
- Include relationships as optional properties
- Use proper date types (string for ISO dates)
- Define union types for enums and status fields

## 🧪 Testing Standards

### Required Test Types
- **Feature Tests**: Complete user workflows
- **Unit Tests**: Individual functions and methods
- **Component Tests**: Vue component behavior
- **API Tests**: API endpoints and responses

### Test Structure (Using Pest)
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

## ⚠️ Error Handling

### Frontend Error Handling
- Use try-catch blocks for async operations
- Display user-friendly error messages using toast notifications
- Handle form validation errors with proper field highlighting
- Implement loading states during API calls

### Backend Error Handling
- Use Form Requests for validation with custom error messages
- Return consistent error responses with proper HTTP status codes
- Log errors using Laravel's logging system
- Handle database exceptions gracefully

## 🚀 Performance Guidelines

### Database Optimization
- Use eager loading to prevent N+1 queries
- Implement proper indexing on frequently queried columns
- Use pagination for large datasets
- Use database transactions for multi-step operations

### Frontend Performance
- Implement lazy loading for large components
- Use computed properties instead of methods for reactive data
- Debounce user inputs for search and filters
- Use preserveState and preserveScroll for better UX

## 🔒 Security Requirements

### Authentication & Authorization
- Verify user authentication on all protected routes
- Implement permission checks using middleware
- Validate user permissions in controllers
- Use CSRF protection for all forms

### Data Validation & Sanitization
- Validate all user inputs on both frontend and backend
- Sanitize data before database storage
- Validate file uploads with proper type and size checks
- Escape output to prevent XSS attacks

## 🛠️ Development Workflow

### Daily Development
```bash
# Start development environment
./dev.sh start

# Or traditional development
composer dev

# Frontend development with hot reload
npm run dev
```

### Testing Workflow
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/UserControllerTest.php

# Frontend type checking
npm run type-check

# Linting
npm run lint
```

### Pre-commit Checklist
- [ ] Run all tests and ensure they pass
- [ ] Check code formatting with Prettier and ESLint
- [ ] Verify TypeScript compilation without errors
- [ ] Test functionality in browser manually
- [ ] Check for console errors and warnings

## 📋 Implementation Checklists

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

## 🔗 Related Documentation

- **[Installation Guide](installation-en.md)** - Setup and installation instructions
- **[Deployment Guide](deployment-en.md)** - Production deployment procedures
- **[API Documentation](api-documentation-en.md)** - API endpoints and integration
- **[User Guide](user-guide-en.md)** - System functionality and usage

## 📖 Additional Resources

- **Laravel Documentation**: https://laravel.com/docs
- **Vue.js Documentation**: https://vuejs.org/guide/
- **Inertia.js Documentation**: https://inertiajs.com/
- **Tailwind CSS Documentation**: https://tailwindcss.com/docs
- **shadcn/ui Vue Documentation**: https://www.shadcn-vue.com/

---

*Last updated: {{ date('Y-m-d') }}*
