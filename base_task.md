# Standard CRUD Workflow Task (Laravel + Inertia + Vue.js)

This document outlines the standard step-by-step process for creating a new CRUD (Create, Read, Update, Delete) module within the Swinburne project. Following this checklist ensures consistency, maintainability, and adherence to our established development standards.

---

## 1. Backend Setup (Laravel)

-   [ ] **Create Migration:**
    -   Define the database schema for the new resource.
    -   Use descriptive and consistent field names (e.g., `curriculum_version_id` instead of `curriculum_id`).
    -   Add foreign key constraints with `->constrained()->onDelete('cascade')` where appropriate.
    -   Run `php artisan migrate`.

-   [ ] **Create Model:**
    -   Define the `$fillable` array with all mass-assignable attributes.
    -   Set up `$casts` for data types like dates, enums, or booleans.
    -   Implement all `BelongsTo` and `HasMany`/`HasOne` relationships.
    -   Create a static `validationRules()` method returning Laravel validation rules.

-   [ ] **Create Controller:**
    -   Generate a resource controller: `php artisan make:controller YourResourceController --resource`.
    -   **`index` method:**
        -   Fetch paginated data (`->paginate(15)`).
        -   Apply filtering and sorting based on request query parameters.
        -   Use eager loading (`->with([...])`) to prevent N+1 issues.
        -   Return an Inertia response, rendering the `Index.vue` page and passing the paginated data and filters.
    -   **`create` method:**
        -   Return an Inertia response, rendering the `Create.vue` page.
        -   Pass any necessary data for dropdowns (e.g., related models).
    -   **`store` method:**
        -   Use the `StoreYourResourceRequest` for validation.
        -   Create the new resource using `YourResource::create($request->validated())`.
        -   Redirect to the `index` route with a success flash message.
    -   **`edit` method:**
        -   Fetch the specific resource by its ID.
        -   Return an Inertia response, rendering the `Edit.vue` page, passing the resource data as a prop.
    -   **`update` method:**
        -   Use the `UpdateYourResourceRequest` for validation.
        -   Find the resource and update it with `$request->validated()`.
        -   Redirect to the `index` route with a success flash message.
    -   **`destroy` method:**
        -   Find the resource by its ID.
        -   Perform checks for related records if necessary before deletion.
        -   Delete the resource.
        -   Redirect back to the `index` route with a success flash message.

-   [ ] **Create Form Requests:**
    -   Create `StoreYourResourceRequest.php`.
    -   Create `UpdateYourResourceRequest.php`.
    -   In both requests:
        -   Set `authorize()` to `true` or implement permission logic.
        -   Define the `rules()` by referencing the model's static rules and adding any specifics.

-   [ ] **Define Routes:**
    -   In a dedicated route file (e.g., `routes/your_resource.php`), define the resource routes using `Route::resource('your-resource', YourResourceController::class)`.
    -   Ensure the route file is registered in `routes/web.php`.
    -   Assign names to all routes for use with Ziggy.

-   [ ] **(Optional) Create Factory and Seeder:**
    -   Create a factory for the model to assist with testing and database seeding.
    -   Create a seeder to populate the database with initial data if needed.

## 2. Frontend Setup (Vue.js + Inertia)

-   [ ] **Define TypeScript Interface:**
    -   In `resources/js/types/models.ts`, add a new TypeScript interface for the resource model.
    -   Ensure property names match the database columns and model attributes.
    -   Include optional properties for relationships (`relation?: RelatedModel`).

-   [ ] **Create `Index.vue` Page:**
    -   Create the file at `resources/js/pages/YourResource/Index.vue`.
    -   Implement the `DataTable.vue` component.
    -   Define `ColumnDef` for the table columns.
    -   Add action buttons/links for "Edit" and a trigger for "Delete" in the actions column.
    -   Implement filtering using the `DebouncedInput.vue` component.
    -   Implement pagination using the `DataPagination.vue` component.

-   [ ] **Create `Create.vue` Page:**
    -   Create the file at `resources/js/pages/YourResource/Create.vue`.
    -   Use the standard form pattern with `vee-validate` and Zod.
    -   Create a Zod schema that matches the backend validation rules.
    -   Use the `Form`, `FormField`, and `reka-ui` components to build the form.
    -   On submit, use `Inertia.post()` to send data to the `store` route.
    -   Ensure the submit button's state is disabled based on form validity and processing state.

-   [ ] **Create `Edit.vue` Page:**
    -   Create the file at `resources/js/pages/YourResource/Edit.vue`.
    -   This page will be very similar to `Create.vue`.
    -   Accept the resource object as a prop.
    -   Use the prop data to set the `initial-values` of the form.
    -   On submit, use `Inertia.put()` to send data to the `update` route.

-   [ ] **Implement Delete Logic:**
    -   Typically handled in `Index.vue`.
    -   On delete button click, show a confirmation dialog (e.g., using `AlertDialog`).
    -   On confirmation, use `Inertia.delete()` to send a request to the `destroy` route.
    -   Use `preserveScroll: true` to avoid jumping to the top of the page after deletion.

## 3. Integration and Final Touches

-   [ ] **Update Navigation:**
    -   Add the new route to the `resources/js/utils/routes.ts` utility file.
    -   Add a new menu item to `resources/js/constants/menu-sidebar.ts` that links to the resource's index page.

-   [ ] **Define Permissions:**
    -   If using `spatie/laravel-permission`, create permissions for `view`, `create`, `edit`, `delete` for the new resource.
    -   Add authorization checks in the controller methods (`$this->authorize(...)`) or Form Requests.

-   [ ] **Review and Test:**
    -   Thoroughly test all CRUD operations.
    -   Verify that all form validation (frontend and backend) works as expected.
    -   Check that success and error messages are displayed correctly.
    -   Ensure the UI is responsive and consistent with the rest of the application. 