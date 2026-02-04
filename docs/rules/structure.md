---
alwaysApply: false
---

# Project Structure & Organization

## Directory Structure

### Backend (Laravel)

- **app/**
    - **Modules/**: Domain modules (Actions, Http, Policies, routes)
    - **Http/**
        - **Controllers/**: Shared/legacy controllers (if any)
        - **Requests/**: Form request validation classes
        - **Resources/**: API resource transformers
    - **Models/**: Shared Eloquent models for database entities
    - **Policies/**: Authorization policies
    - **Providers/**: Service providers
    - **Services/**: Legacy technical services (avoid business logic here)
    - **Traits/**: Reusable traits

### Frontend (Vue.js)

- **resources/**
    - **css/**: Global CSS styles
    - **js/**
        - **components/**: Reusable Vue components
        - **composables/**: Reusable Vue composition functions
        - **constants/**: Frontend constants
        - **directives/**: Custom Vue directives
        - **layouts/**: Page layout components
        - **pages/**: Vue pages organized by feature
        - **stores/**: Pinia stores
        - **types/**: TypeScript interfaces and types
        - **utils/**: Utility functions
        - **app.ts**: Main application entry point
        - **ziggy.js**: Route definitions for frontend

### Configuration

- **config/**: Laravel configuration files
    - **permission.php**: Centralized permission definitions

### Database

- **database/**
    - **factories/**: Model factories for testing and seeding
    - **migrations/**: Database migrations
    - **seeders/**: Database seeders

## Naming Conventions

### Backend

- **Controllers**: PascalCase with `Controller` suffix (e.g., `UserController`)
- **Models**: PascalCase singular (e.g., `User`, `CourseOffering`)
- **Database Tables**: snake_case plural (e.g., `users`, `course_offerings`)
- **Database Columns**: snake_case (e.g., `first_name`, `created_at`)

### Frontend

- **Vue Components**: PascalCase (e.g., `DataTable.vue`, `UserForm.vue`)
- **Composables**: camelCase with `use` prefix (e.g., `usePermission`)
- **Types/Interfaces**: PascalCase (e.g., `User`, `PaginatedResponse`)
- **Store Files**: camelCase (e.g., `userStore.ts`)

### Routes

- **API Routes**: kebab-case (e.g., `/api/course-offerings`)
- **Web Routes**: kebab-case (e.g., `/curriculum-versions`)
- **Route Names**: dot notation (e.g., `users.index`, `roles.create`)

## Architecture Patterns

### Permission System

- Centralized permission definitions in `config/permission.php`
- Automatic gate registration via `PermissionServiceProvider`
- Route permission helpers for consistent authorization

### Form Validation

- Backend: Form Request classes for validation
- Frontend: Vee-validate with Zod schemas
- Validation rules should be consistent between frontend and backend

### Data Tables

- Use the standard `DataTable.vue` component
- Include pagination, sorting, and filtering
- Follow the established pattern for API responses

### Type Management

- Store all TypeScript interfaces in `resources/js/types/`
- Match backend model structure in frontend interfaces
- Use proper typing for API responses and requests
