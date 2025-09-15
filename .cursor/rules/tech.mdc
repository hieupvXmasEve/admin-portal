---
alwaysApply: false
---
# Technology Stack & Build System

## Backend
- **Framework**: Laravel 12
- **PHP Version**: 8.4+
- **Web Server**: FrankenPHP (modern PHP application server)
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Authentication**: Laravel Sanctum + Socialite
- **Testing**: Pest PHP testing framework

## Frontend
- **Framework**: Vue.js 3 with TypeScript
- **Build Tool**: Vite with HMR
- **UI Library**: shadcn-vue + Reka-UI (based on shadcn/ui) + TailwindCSS
- **Forms**: Vee-validate with Zod schemas
- **Tables**: TanStack Table
- **Icons**: Lucide Icons
- **State Management**: Pinia

## Development Tools
- **Code Quality**: ESLint, Prettier
- **Type Checking**: TypeScript, Vue-TSC
- **Version Control**: Git
- **CI/CD**: GitHub Actions

## Common Commands

### Development Environment
```bash
# Traditional development (without Docker)
composer dev

# Frontend development with hot reload
npm run dev
```

### Testing
```bash
# Run all PHP tests
php artisan test

# Run specific test file
php artisan test tests/Feature/UserControllerTest.php

# Frontend type checking
npm run type-check

# Linting and formatting
npm run lint
npm run format
```

### Database
```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

### Code Generation
```bash
# Create controller
php artisan make:controller UserController --resource

# Create model with migration and factory
php artisan make:model User -mf

# Create module routes
php artisan make:module-routes users UserController
```
