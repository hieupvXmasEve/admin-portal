---
inclusion: manual
---

# Code Generation Standards

Strict rules for all AI-generated code in this Vue 3 + Inertia + Laravel project.

## Code Quality

### Linting & Formatting

- All code must pass ESLint and Prettier validation
- Fix formatting errors before presenting final output
- Follow Airbnb JavaScript style guide conventions
- Use consistent indentation (2 spaces) and semicolons

### TypeScript Standards

- Explicit type annotations for function parameters and return types
- Use interfaces over type aliases for object shapes
- Prefer `const` over `let`, avoid `var` entirely
- Import types with `import type` when possible

## Vue.js Conventions

### Component Structure

- Use `<script setup>` syntax for all new components
- Order script blocks: imports, props, emits, composables, reactive data, computed, methods
- Use PascalCase for component names and file names
- Prefer composition API over options API

### Template Guidelines

- Use kebab-case for HTML attributes and event handlers
- Prefer `v-show` for conditional rendering that toggles frequently
- Use `v-if` for conditional rendering that rarely changes
- Always use `:key` with `v-for` loops

### Reka-UI Components

- Verify component exists in `@/components/ui/` before use
- Add TODO comment if component needs installation: `<!-- TODO: Run npx shadcn-vue add [component] -->`
- Always import components explicitly
- Use proper TypeScript props interface

## Laravel Conventions

### Controllers

- Keep controllers thin - delegate business logic to services
- Use form request classes for validation
- Return consistent API response format with proper HTTP status codes
- Follow RESTful naming conventions

### Models & Database

- Use snake_case for database columns and table names
- Use PascalCase for model names (singular)
- Define relationships explicitly with proper return types
- Use database transactions for multi-step operations

### Services

- Encapsulate complex business logic in service classes
- Use dependency injection for service dependencies
- Handle exceptions appropriately with meaningful error messages
- Return consistent data structures

## Permission System

- Use centralized permission definitions from `config/permission.php`
- Apply route permissions via `RoutePermissionHelper`
- Check permissions in controllers using gates or policies
- Include permission checks in frontend components when needed

## Error Handling

- Use try-catch blocks for operations that may fail
- Provide meaningful error messages to users
- Log errors appropriately for debugging
- Handle validation errors consistently

## Required TODOs

Add TODO comments for:

- Missing route registrations
- Required database migrations
- Missing component installations
- Needed permission definitions
- Required test cases

## Testing Requirements

- Include unit test placeholders for new services
- Add integration test comments for new API endpoints
- Consider edge cases in validation logic
- Test permission-based access controls
