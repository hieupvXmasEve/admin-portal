---
alwaysApply: true
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
# Start Docker dev stack
./scripts/dev.sh start

# Stop Docker dev stack
./scripts/dev.sh stop

# Inspect Docker dev stack
./scripts/dev.sh status

# Frontend development with hot reload
./scripts/dev.sh npm run dev
```

### Docker-First Rule

- Local backend runtime is Docker-based.
- Do not assume `php`, `composer`, `npm`, or `pnpm` are installed on the host machine.
- Prefer `./scripts/dev.sh artisan ...`, `./scripts/dev.sh composer ...`, `./scripts/dev.sh npm ...`, and `./scripts/dev.sh test ...`.
- Use direct host commands only when the task is explicitly host-only.

### Testing

```bash
# Run all PHP tests
./scripts/dev.sh test

# Run specific test file
./scripts/dev.sh test tests/Feature/UserControllerTest.php

# Frontend type checking
./scripts/dev.sh npm run type-check

# Linting and formatting
./scripts/dev.sh npm run lint
./scripts/dev.sh npm run format
```

### Database

```bash
# Run migrations
./scripts/dev.sh artisan migrate

# Seed database
./scripts/dev.sh artisan db:seed

# Fresh migration with seeding
./scripts/dev.sh artisan migrate:fresh --seed
```

### Code Generation

```bash
# Create controller
./scripts/dev.sh artisan make:controller UserController --resource

# Create model with migration and factory
./scripts/dev.sh artisan make:model User -mf

# Create module routes
./scripts/dev.sh artisan make:module-routes users UserController
```
