# Route Management System Documentation

## Overview

The Swinburne Education Management System implements a centralized route management system that integrates Laravel named routes with Ziggy.js and the frontend navigation menu. This system provides type safety, consistency, and maintainability across the entire application.

## System Components

### 1. Shared Routes Utility (`resources/js/utils/routes.ts`)

This is the central hub for all route management in the application. It provides:

- **Type-safe route generation** using Ziggy.js
- **Organized route categories** by functionality
- **Consistent parameter handling** 
- **Placeholder support** for future features
- **Helper functions** for route matching and navigation

#### Key Features:

```typescript
// Type-safe route generation
export const systemRoutes = {
  users: {
    index: () => route('user.index'),
    create: () => route('user.create'),
    edit: (id: number) => route('user.edit', { user: id }),
    show: (id: number) => route('user.show', { user: id }),
  },
  // ... more routes
};

// Helper functions
export const isActiveRoute = (routeName: string, currentRoute?: string) => {
  return currentRoute === routeName || currentRoute.startsWith(`${routeName}.`);
};
```

### 2. Menu Sidebar Integration (`resources/js/constants/menu-sidebar.ts`)

The navigation menu now uses the shared routes utility:

```typescript
import {
    systemRoutes,
    studentRoutes,
    curriculumRoutes,
    // ... other route categories
} from '@/utils/routes';

export const mainNavItems: NavItem[] = [
    {
        title: 'System Management',
        href: '#',
        icon: Settings,
        children: [
            {
                title: 'Users',
                href: systemRoutes.users.index(),
                icon: Users,
                requiredPermissions: ['view_user'],
            },
            // ... more menu items
        ],
    },
    // ... more categories
];
```

### 3. Laravel Named Routes Integration

All routes are referenced using Laravel's named route system via Ziggy.js:

```php
// routes/user.php
Route::get('/users', [UserController::class, 'index'])->name('user.index');
Route::get('/users/create', [UserController::class, 'create'])->name('user.create');
Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('user.edit');
```

## Route Categories

### 1. System Management Routes
- **Users**: User CRUD operations, import/export
- **Roles**: Role and permission management
- **Semesters**: Academic term management
- **Campuses**: Campus and department management (planned)
- **Classrooms**: Classroom management (planned)

### 2. Student Management Routes
- **Student List**: Student CRUD operations
- **Academic Records**: Student academic history (planned)
- **Program Changes**: Program/specialization transfers (planned)
- **Repeat Courses**: Course retaking system (planned)
- **Academic Standing**: Student status tracking (planned)
- **Enrollments**: Student enrollment management (planned)

### 3. Lecturer Management Routes
- **Lecturer List**: Lecturer management (planned)
- **Teaching Assignments**: Course-lecturer mapping (planned)
- **Timetables**: Lecturer schedule management (planned)

### 4. Curriculum & Courses Routes
- **Programs**: Degree program management
- **Specializations**: Specialization management
- **Curriculum Versions**: Curriculum version control
- **Units**: Course/unit management
- **Equivalent Courses**: Course equivalency mapping (planned)
- **Curriculum Structure**: Structure visualization (planned)

### 5. Course Offerings & Registration Routes
- **Course Offerings**: Course offering management
- **Room Assignment**: Room and instructor assignment (planned)
- **Class Schedule**: Scheduling system (planned)
- **Course Registration**: Student course registration
- **Enrollment Summary**: Registration statistics (planned)

### 6. Attendance Management Routes
- **Class Sessions**: Session management (planned)
- **Take Attendance**: Attendance recording (planned)
- **Attendance Reports**: Attendance analytics (planned)
- **GPS Tracking**: Location-based attendance (planned)

### 7. Assessments & Grading Routes
- **Assessment Components**: Assessment structure (planned)
- **Enter Grades**: Grade entry system (planned)
- **Academic Results**: Grade viewing (planned)
- **Re-assessments**: Reassessment management (planned)
- **Grade Reports**: Grade reporting (planned)
- **GPA History**: GPA tracking (planned)

### 8. Academic Summary Routes
- **GPA Calculations**: GPA computation (planned)
- **Degree Classification**: Degree classification (planned)
- **Transcript History**: Transcript management (planned)
- **Performance Reports**: Academic performance analysis (planned)

### 9. Program Transfers & Course Retakes Routes
- **Program Change Requests**: Program transfer requests (planned)
- **Repeated Courses**: Course retaking tracking (planned)
- **Substitute Course Mapping**: Course substitution (planned)
- **Credit Transfer Evaluation**: Credit transfer assessment (planned)

### 10. Reports & Analytics Routes
- **Student Statistics**: Student data analytics (planned)
- **Performance Summary**: Academic performance reporting (planned)
- **Attendance Summary**: Attendance reporting (planned)
- **GPA Distribution**: GPA analytics (planned)

### 11. Course Syllabus & Content Routes
- **Syllabi Management**: Syllabus CRUD operations
- **Learning Materials**: Course materials (planned)
- **Learning Outcomes**: Learning objective management (planned)
- **Assessment Rubrics**: Rubric management (planned)

## Usage Examples

### 1. Navigation in Components

```typescript
// In a Vue component
import { systemRoutes } from '@/utils/routes';

// Navigate to user index
router.visit(systemRoutes.users.index());

// Navigate to edit user
router.visit(systemRoutes.users.edit(userId));
```

### 2. Active Route Checking

```typescript
import { isActiveRoute } from '@/utils/routes';

// Check if current route is active
const isActive = isActiveRoute('user.index', currentRoute);

// Check multiple routes
const isUserSection = isActiveRoutes(['user.index', 'user.create', 'user.edit'], currentRoute);
```

### 3. Dynamic Route Generation

```typescript
import { generateRoute } from '@/utils/routes';

// Generate route with parameters
const editRoute = generateRoute('user.edit', { user: 123 });
```

## Benefits

### 1. Type Safety
- **Compile-time checking** for route parameters
- **IntelliSense support** for route names and parameters
- **Reduced runtime errors** from typos in route names

### 2. Maintainability
- **Single source of truth** for route definitions
- **Easy refactoring** when routes change
- **Consistent naming conventions** across the application

### 3. Developer Experience
- **Autocomplete support** for route names
- **Clear organization** by functionality
- **Easy to find** and modify routes

### 4. Scalability
- **Modular structure** for easy extension
- **Placeholder support** for future features
- **Organized by domain** for large applications

## Future Enhancements

### 1. Route Constants Backend
Create Laravel constants that match the frontend route organization:

```php
// app/Constants/RouteConstants.php
class RouteConstants 
{
    public const USER_INDEX = 'user.index';
    public const USER_CREATE = 'user.create';
    public const USER_EDIT = 'user.edit';
    // ... more constants
}
```

### 2. Route Middleware Integration
Integrate route-level permissions with the menu system:

```typescript
// Automatic permission checking based on routes
const hasAccess = checkRoutePermission(systemRoutes.users.index());
```

### 3. Route Caching
Implement route caching for improved performance:

```typescript
// Cache frequently used routes
const cachedRoutes = cacheRoutes(systemRoutes);
```

## Migration Guide

### For Existing Components

1. **Replace hardcoded routes** with route utility functions:
   ```typescript
   // Before
   href: '/users'
   
   // After
   href: systemRoutes.users.index()
   ```

2. **Update route checking logic**:
   ```typescript
   // Before
   const isActive = route().current('users.*');
   
   // After
   const isActive = isActiveRoutes(['user.index', 'user.create'], currentRoute);
   ```

3. **Use helper functions** for navigation:
   ```typescript
   // Before
   router.visit('/users/123/edit');
   
   // After
   router.visit(systemRoutes.users.edit(123));
   ```

### For New Features

1. **Add routes to the utility** before implementation
2. **Update menu items** to use the new routes
3. **Implement Laravel routes** with proper naming
4. **Test route integration** with frontend navigation

## Best Practices

### 1. Route Naming
- Use **consistent naming** patterns: `resource.action`
- Include **resource parameters** in route names
- Use **descriptive names** for complex routes

### 2. Parameter Handling
- Always **type parameters** correctly
- Use **meaningful parameter names**
- Validate **parameter types** at runtime when needed

### 3. Error Handling
- Implement **fallback routes** for missing resources
- Provide **user-friendly error messages**
- Log **route-related errors** for debugging

### 4. Testing
- Test **route generation** with various parameters
- Verify **route matching** logic
- Check **permission integration** with routes

## Conclusion

The centralized route management system provides a robust foundation for navigation and routing in the Swinburne Education Management System. It ensures consistency, type safety, and maintainability while providing excellent developer experience.

The system is designed to grow with the application, supporting future features through placeholder routes and modular organization. This approach significantly reduces development time and potential bugs related to routing and navigation. 
