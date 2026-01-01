# Identity Module

## Overview

The Identity Module is responsible for answering "Who is the user?", "Where are they?" (Campus), and "What is their role/context?". It centralizes authentication state management and provides a unified `IdentityContext`.

## Components

### IdentityContext

`App\Modules\Identity\IdentityContext` is a singleton service that provides access to:

- `user()`: The currently authenticated User.
- `campusId()`: The currently selected Campus ID.
- `roles()`: The roles active for the current campus.
- `isStaff()`, `isStudent()`, `isLecturer()`, `isParent()`: Helper methods checking the user's base type.

### Controllers

- **SocialAuthController**: Handles Google OAuth login via `fpt.edu.vn` domain.
- **CampusSelectionController**: Manages the mandatory campus selection step after login.

### ServiceProvider

`App\Modules\Identity\Providers\IdentityServiceProvider` registers the `IdentityContext` singleton and initializes it.

## Usage

In any controller or service, you can inject `IdentityContext`:

```php
use App\Modules\Identity\IdentityContext;

public function index(IdentityContext $identity)
{
    $campusId = $identity->campusId();
    if ($identity->isTopManager()) {
        // ...
    }
}
```
